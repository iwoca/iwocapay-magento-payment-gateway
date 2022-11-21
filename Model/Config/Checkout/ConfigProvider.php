<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Config\Checkout;

use Iwoca\Iwocapay\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'iwoca_iwocapay';

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @param Config $config
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Config $config,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession
    ) {
        $this->config = $config;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
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
            'sellerAccessKey' => $this->config->getSellerAccessKey(),
            'sellerId' => $this->config->getSellerId(),
            'mode' => $this->config->getMode(),
            'title' => $this->config->getTitle(),
            'currency' => $this->config->getCurrency()
        ];

        return [
            'payment' => [
                self::CODE => $config
            ]
        ];
    }
}
