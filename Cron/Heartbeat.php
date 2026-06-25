<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Cron;

use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\IntegrationEventService;
use Iwoca\Iwocapay\Model\Version;
use Psr\Log\LoggerInterface;

class Heartbeat
{
    private const EVENT_TYPE = 'SELLER_HEARTBEAT';

    private IntegrationEventService $eventService;
    private Config $config;
    private Version $version;
    private LoggerInterface $logger;

    public function __construct(
        IntegrationEventService $eventService,
        Config $config,
        Version $version,
        LoggerInterface $logger
    ) {
        $this->eventService = $eventService;
        $this->config = $config;
        $this->version = $version;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        try {
            $this->eventService->send(self::EVENT_TYPE, [
                'integration_version' => $this->version->get(),
                'mode' => $this->config->getMode(),
                'feature_flags' => [
                    'active' => $this->config->isActive(),
                    'price_banner_enabled' => $this->config->isPriceBannerEnabled(),
                    'debug_mode' => $this->config->isDebugModeEnabled(),
                ],
                'allowed_payment_terms' => $this->config->getAllowedPaymentTerms(),
            ]);

            $this->eventService->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Heartbeat: failed to send heartbeat event: ' . $e->getMessage());
        }
    }
}
