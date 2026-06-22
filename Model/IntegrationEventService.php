<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

use GuzzleHttp\ClientFactory as GuzzleClientFactory;
use GuzzleHttp\RequestOptions;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class IntegrationEventService
{
    private const INTEGRATION_NAME = 'magento';

    private GuzzleClientFactory $guzzleClientFactory;
    private Config $config;
    private Json $jsonSerializer;
    private LoggerInterface $logger;
    private Version $version;
    private array $buffer = [];
    private bool $shutdownRegistered = false;

    public function __construct(
        GuzzleClientFactory $guzzleClientFactory,
        Config $config,
        Json $jsonSerializer,
        LoggerInterface $logger,
        Version $version
    ) {
        $this->guzzleClientFactory = $guzzleClientFactory;
        $this->config = $config;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
        $this->version = $version;
    }

    public function send(string $eventType, array $metaData = []): void
    {
        $this->buffer[] = [
            'event_type' => $eventType,
            'meta_data' => $metaData,
        ];

        if (!$this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            register_shutdown_function([$this, 'flush']);
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

        foreach ($this->buffer as $event) {
            $payload = $this->jsonSerializer->serialize([
                'data' => [
                    'event_type' => $event['event_type'],
                    'integration_name' => self::INTEGRATION_NAME,
                    'integration_version' => $this->version->get(),
                    'meta_data' => !empty($event['meta_data']) ? $event['meta_data'] : new \stdClass(),
                ],
            ]);

            try {
                $client->post($url, ['body' => $payload]);
            } catch (\Exception $e) {
                $this->logger->debug('iwocaPay integration event failed: ' . $e->getMessage());
            }
        }

        $this->buffer = [];
    }
}
