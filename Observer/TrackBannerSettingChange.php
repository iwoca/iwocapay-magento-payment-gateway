<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Observer;

use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\IntegrationEventService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;

class TrackBannerSettingChange implements ObserverInterface
{
    private IntegrationEventService $eventService;
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        IntegrationEventService $eventService,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->eventService = $eventService;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(Observer $observer): void
    {
        $changedPaths = (array) $observer->getEvent()->getData('changed_paths');

        if (!in_array(Config::XML_CONFIG_PATH_PRICE_BANNER_ENABLED, $changedPaths, true)) {
            return;
        }

        $enabled = $this->scopeConfig->isSetFlag(
            Config::XML_CONFIG_PATH_PRICE_BANNER_ENABLED,
            ScopeInterface::SCOPE_STORE
        );

        $pricingType = (string) $this->scopeConfig->getValue(
            Config::XML_CONFIG_PATH_PRICE_BANNER_PRICING,
            ScopeInterface::SCOPE_STORE
        );

        $duration = (string) $this->scopeConfig->getValue(
            Config::XML_CONFIG_PATH_PRICE_BANNER_MONTHS,
            ScopeInterface::SCOPE_STORE
        );

        $this->eventService->send('SELLER_ENABLED_PRICING_BANNERS', [
            'action' => $enabled ? 'enabled' : 'disabled',
            'pricing_type' => $pricingType,
            'pricing_duration' => $duration,
        ]);
    }
}
