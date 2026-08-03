<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Observer;

use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\IntegrationEventService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class TrackBannerSettingChange implements ObserverInterface
{
    private IntegrationEventService $eventService;
    private Config $config;

    public function __construct(
        IntegrationEventService $eventService,
        Config $config
    ) {
        $this->eventService = $eventService;
        $this->config = $config;
    }

    /**
     * The banner config paths worth reporting a change for: both banner
     * surfaces (product/listing + checkout), every per-duration enable +
     * interest, and the shared VAT / theme settings.
     */
    private const WATCHED_PATHS = [
        Config::XML_CONFIG_PATH_PRICE_BANNER_ENABLED,
        Config::XML_CONFIG_PATH_PRICE_BANNER_CHECKOUT_ENABLED,
        Config::XML_CONFIG_PATH_PRICE_BANNER_30D_ENABLED,
        Config::XML_CONFIG_PATH_PRICE_BANNER_30D_INTEREST,
        Config::XML_CONFIG_PATH_PRICE_BANNER_3M_ENABLED,
        Config::XML_CONFIG_PATH_PRICE_BANNER_3M_INTEREST,
        Config::XML_CONFIG_PATH_PRICE_BANNER_12M_ENABLED,
        Config::XML_CONFIG_PATH_PRICE_BANNER_12M_INTEREST,
        Config::XML_CONFIG_PATH_PRICE_BANNER_VAT,
        Config::XML_CONFIG_PATH_PRICE_BANNER_THEME,
    ];

    public function execute(Observer $observer): void
    {
        $changedPaths = (array) $observer->getEvent()->getData('changed_paths');

        if (!array_intersect(self::WATCHED_PATHS, $changedPaths)) {
            return;
        }

        $this->eventService->send('SELLER_ENABLED_PRICING_BANNERS', [
            'surfaces' => [
                'product' => $this->config->isPriceBannerEnabled(),
                'checkout' => $this->config->isCheckoutBannerEnabled(),
            ],
            'durations' => $this->config->getAllBannerDurations(),
            'vat' => $this->config->getPriceBannerVat(),
            'theme' => $this->config->getPriceBannerTheme(),
        ]);
    }
}
