<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Controller\Banner;

use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\IntegrationEventService;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Psr\Log\LoggerInterface;

class Pricing implements HttpGetActionInterface
{
    private const CACHE_PREFIX = 'iwocapay_instalment_';
    private const CACHE_TTL = 86400;
    private const RATE_MAP = [
        3 => '0.025',
        12 => '0.02',
    ];

    private JsonFactory $jsonFactory;
    private Config $config;
    private RequestInterface $request;
    private CacheInterface $cache;
    private ClientFactory $httpClientFactory;
    private LoggerInterface $logger;
    private SessionManagerInterface $session;
    private IntegrationEventService $eventService;

    public function __construct(
        JsonFactory $jsonFactory,
        Config $config,
        RequestInterface $request,
        CacheInterface $cache,
        ClientFactory $httpClientFactory,
        LoggerInterface $logger,
        SessionManagerInterface $session,
        IntegrationEventService $eventService
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->config = $config;
        $this->request = $request;
        $this->cache = $cache;
        $this->httpClientFactory = $httpClientFactory;
        $this->logger = $logger;
        $this->session = $session;
        $this->eventService = $eventService;
    }

    public function execute()
    {
        $this->session->writeClose();
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'public, max-age=' . self::CACHE_TTL, true);
        $result->setHeader('Pragma', 'cache', true);
        $result->setHeader('Expires', '', true);

        $amount = (float) $this->request->getParam('amount', 0);
        $months = (int) $this->request->getParam('months', 3);
        $pricing = $this->config->getPriceBannerPricing();

        if ($amount < 5 || !isset(self::RATE_MAP[$months])) {
            $result->setData(['repayment_amount' => null]);
            return $result;
        }

        if ($pricing === 'seller_pays') {
            $repaymentAmount = round($amount / $months, 2);
            $result->setData(['repayment_amount' => $repaymentAmount]);
            return $result;
        }

        $rate = self::RATE_MAP[$months];
        $cacheKey = self::CACHE_PREFIX . md5($rate . '_' . $amount . '_' . $months);

        $cached = $this->cache->load($cacheKey);
        if ($cached !== false) {
            $result->setData(['repayment_amount' => (float) $cached]);
            return $result;
        }

        $repaymentAmount = $this->fetchRepaymentAmount($rate, $amount, $months);

        if ($repaymentAmount !== null) {
            $this->cache->save((string) $repaymentAmount, $cacheKey, ['iwocapay_pricing'], self::CACHE_TTL);
        }

        $result->setData(['repayment_amount' => $repaymentAmount]);
        return $result;
    }

    private function fetchRepaymentAmount(string $rate, float $amount, int $months): ?float
    {
        $baseUrl = $this->config->getBaseUrl();
        $url = rtrim($baseUrl, '/') . '/api/lending/edge/example_repayment_schedule/';
        $url .= '?' . http_build_query([
            '30_day_rate' => $rate,
            'initial_principal' => $amount,
            'loan_duration_months' => $months,
            'product' => 'iwocapay',
            'promotions' => 'equal_repayments',
        ]);

        $startTime = microtime(true);
        $result = null;
        $error = false;

        try {
            $client = $this->httpClientFactory->create();
            $client->setTimeout(5);
            $client->get($url);

            if ($client->getStatus() !== 200) {
                $this->logger->error('iwocaPay pricing API returned status ' . $client->getStatus());
                $error = true;
            } else {
                $data = json_decode($client->getBody(), true);
                $result = isset($data['data'][0]['total']) ? (float) $data['data'][0]['total'] : null;
            }
        } catch (\Exception $e) {
            $this->logger->error('iwocaPay pricing API error: ' . $e->getMessage());
            $error = true;
        }

        $meta = [
            'request_duration_ms' => round((microtime(true) - $startTime) * 1000),
            'amount' => $amount,
            'loan_duration' => $months,
            'rate' => $rate,
            'result' => $result,
        ];
        if ($error) {
            $meta['error'] = true;
        }
        $this->eventService->send('PRICING_BANNER_LOADED', $meta);

        return $result;
    }
}
