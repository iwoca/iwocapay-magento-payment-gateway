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
    private Config $config;

    public function __construct(
        IntegrationEventService $eventService,
        ScopeConfigInterface $scopeConfig,
        Config $config
    ) {
        $this->eventService = $eventService;
        $this->scopeConfig = $scopeConfig;
        $this->config = $config;
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

        $terms = array_map(
            static fn (array $term): array => [
                'duration' => $term['duration'],
                'interest' => $term['interest'],
            ],
            $this->config->getEnabledBannerTerms()
        );

        $this->eventService->send('SELLER_ENABLED_PRICING_BANNERS', [
            'action' => $enabled ? 'enabled' : 'disabled',
            'terms' => $terms,
            'vat' => $this->config->getPriceBannerVat(),
        ]);
    }
}
