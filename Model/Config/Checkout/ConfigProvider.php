<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Config\Checkout;

use Iwoca\Iwocapay\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE_PAYLATER = 'iwocapay_paylater';
    public const CODE_PAYNOW = 'iwocapay_paynow';

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Repository
     */
    private Repository $assetRepository;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @param Config $config
     * @param Repository $assetRepository
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        Config $config,
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
            'payment' => [
                self::CODE_PAYLATER => [
                    'isActive' => $this->config->isActive(self::CODE_PAYLATER),
                    'title' => $this->config->getTitle(self::CODE_PAYLATER),
                    'iconSrc' => $this->assetRepository->getUrlWithParams('Iwoca_Iwocapay::images/iwocapay-icon.png', []),
                    'iwocaCreateOrderUrl' => $this->urlBuilder->getRouteUrl('iwocapay/process/createOrder'),
                    'minAmount' => 0, // You might have different min/max amounts for each payment type
                    'maxAmount' => 999999,
                ],
                self::CODE_PAYNOW => [
                    'isActive' => $this->config->isActive(self::CODE_PAYNOW),
                    'title' => $this->config->getTitle(self::CODE_PAYNOW),
                    'iconSrc' => $this->assetRepository->getUrlWithParams('Iwoca_Iwocapay::images/iwocapay-icon.png', []),
                    'iwocaCreateOrderUrl' => $this->urlBuilder->getRouteUrl('iwocapay/process/createOrder'),
                    'minAmount' => 0,
                    'maxAmount' => 999999,
                ],
            ],
        ];

        return $config;
    }
}
