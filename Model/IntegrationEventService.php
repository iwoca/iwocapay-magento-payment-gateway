<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

use GuzzleHttp\ClientFactory as GuzzleClientFactory;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class IntegrationEventService
{
    private const INTEGRATION_NAME = 'magento';

    public const EVENT_ORDER_CREATE_FAILED = EventReportPolicy::EVENT_ORDER_CREATE_FAILED;
    public const EVENT_ORDER_FAILED_TO_RECONCILE = EventReportPolicy::EVENT_ORDER_FAILED_TO_RECONCILE;
    public const EVENT_API_CALL_FAILED = EventReportPolicy::EVENT_API_CALL_FAILED;
    public const EVENT_UNHANDLED_EXCEPTION = EventReportPolicy::EVENT_UNHANDLED_EXCEPTION;

    private GuzzleClientFactory $guzzleClientFactory;
    private Config $config;
    private Json $jsonSerializer;
    private LoggerInterface $logger;
    private Version $version;
    private EventReportPolicy $policy;
    private array $buffer = [];
    private bool $shutdownRegistered = false;
    private bool $bufferOverflowed = false;

    public function __construct(
        GuzzleClientFactory $guzzleClientFactory,
        Config $config,
        Json $jsonSerializer,
        LoggerInterface $logger,
        Version $version,
        EventReportPolicy $policy
    ) {
        $this->guzzleClientFactory = $guzzleClientFactory;
        $this->config = $config;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
        $this->version = $version;
        $this->policy = $policy;
    }

    public function send(string $eventType, array $metaData = []): void
    {
        // Cap the buffer so a systemic failure across a large cron batch (e.g. an
        // expired token 401ing every order) can't queue an unbounded number of
        // POSTs to run synchronously at CLI shutdown, where fastcgi_finish_request()
        // doesn't exist. Overflow is dropped and warned about once.
        if ($this->policy->isBufferFull(count($this->buffer))) {
            if (!$this->bufferOverflowed) {
                $this->bufferOverflowed = true;
                $this->logger->warning(sprintf(
                    'iwocaPay integration events buffer reached %d; dropping further events this request.',
                    EventReportPolicy::MAX_BUFFERED_EVENTS
                ));
            }
            return;
        }

        $this->buffer[] = [
            'event_type' => $eventType,
            'meta_data' => $metaData,
        ];

        if (!$this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            register_shutdown_function([$this, 'flush']);
        }
    }

    public function shouldSendForException(\Throwable $exception): bool
    {
        return $this->policy->shouldSendForStatus($this->statusCodeFromException($exception));
    }

    public function shouldSendForStatus(?int $statusCode): bool
    {
        return $this->policy->shouldSendForStatus($statusCode);
    }

    public function isKnownOrderStatus(?string $status): bool
    {
        return $this->policy->isKnownOrderStatus($status);
    }

    public function apiErrorContext(\Throwable $exception): array
    {
        $statusCode = $this->statusCodeFromException($exception);

        $detail = null;
        if ($exception instanceof RequestException && $exception->hasResponse()) {
            $detail = $this->policy->extractErrorDetail((string) $exception->getResponse()->getBody());
        }

        return [
            'http_status' => $statusCode,
            'error_detail' => $detail,
            'exception_message' => mb_substr($exception->getMessage(), 0, EventReportPolicy::MAX_DETAIL_LENGTH),
        ];
    }

    public function extractResponseErrorDetail(ResponseInterface $response): ?string
    {
        return $this->policy->extractErrorDetail((string) $response->getBody());
    }

    private function statusCodeFromException(\Throwable $exception): ?int
    {
        if ($exception instanceof RequestException && $exception->hasResponse()) {
            return $exception->getResponse()->getStatusCode();
        }

        return null;
    }

    public function report(string $eventType, array $metaData = [], bool $send = true): void
    {
        $this->logger->log(
            $this->policy->logLevelFor($eventType),
            sprintf('iwocaPay %s %s', $eventType, $this->jsonSerializer->serialize($metaData))
        );

        if ($send) {
            $this->send($eventType, $metaData);
        }
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $sellerId = $this->config->getSellerId();
        $accessToken = $this->config->getSellerAccessToken();

        if (!$sellerId || !$accessToken) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        $url = rtrim($this->config->getBaseUrl(), '/')
            . '/api/iwocapay/seller/integrations/' . $sellerId . '/events/';

        $client = $this->guzzleClientFactory->create(['config' => [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            RequestOptions::TIMEOUT => 2,
            RequestOptions::CONNECT_TIMEOUT => 1,
        ]]);

        $events = array_map(
            fn (array $event): array => [
                'event_type' => $event['event_type'],
                'integration_name' => self::INTEGRATION_NAME,
                'integration_version' => $this->version->get(),
                'meta_data' => !empty($event['meta_data']) ? $event['meta_data'] : new \stdClass(),
            ],
            $this->buffer
        );

        $this->buffer = [];

        foreach ($this->policy->chunk($events) as $chunk) {
            $payload = $this->jsonSerializer->serialize(['events' => $chunk]);

            try {
                $client->post($url, ['body' => $payload]);
            } catch (\Exception $e) {
                $this->logger->warning('iwocaPay integration events failed to send: ' . $e->getMessage());
            }
        }
    }
}
