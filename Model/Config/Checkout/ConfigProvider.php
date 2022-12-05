<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Config\Checkout;

use Iwoca\Iwocapay\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'iwocapay';

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Repository
     */
    private Repository $assetRepository;
    private UrlInterface $urlBuilder;

    /**
     * @param Config $config
     */
    public function __construct(
        Config          $config,
        Repository $assetRepository,
        UrlInterface $urlBuilder
    ) {
        $this->config = $config;
        $this->assetRepository = $assetRepository;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $config = [
            'isActive' => $this->config->isActive(),
            'sellerAccessToken' => $this->config->getSellerAccessToken(),
            'sellerId' => $this->config->getSellerId(),
            'mode' => $this->config->getMode(),
            'title' => $this->config->getTitle(),
            'currency' => $this->config->getCurrency(),
            'iconSrc' => $this->assetRepository->getUrlWithParams('Iwoca_Iwocapay::images/iwocapay-icon.png', []),
            'iwocaCreateOrderUrl' => $this->urlBuilder->getRouteUrl('iwocapay/process/createOrder')
        ];

        return [
            'payment' => [
                self::CODE => $config
            ]
        ];
    }
}
