<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Config\Checkout;

use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\Config\Source\PaymentTerms;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
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

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @param Config $config
     * @param Repository $assetRepository
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        Config $config,
        Repository $assetRepository,
        UrlInterface $urlBuilder,
        Session $checkoutSession
    ) {
        $this->config = $config;
        $this->assetRepository = $assetRepository;
        $this->urlBuilder = $urlBuilder;
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
            'isActive' => $this->isActive(),
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

    /**
     * Check if the payment method is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        if (!$this->config->isActive()) {
            return false;
        }

        $allowedPaymentTerms = $this->config->getAllowedPaymentTerms();
        $quoteTotal = (float) $this->checkoutSession->getQuote()->getSubtotalWithDiscount();
        if ($allowedPaymentTerms === PaymentTerms::PAY_LATER && !$this->isQuoteTotalInRange($quoteTotal)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the quote total falls within the allowed price range for pay later
     *
     * @param float $quoteTotal
     *
     * @return bool
     */
    public function isQuoteTotalInRange(float $quoteTotal): bool
    {
        return $quoteTotal >= PaymentTerms::PAY_LATER_MIN_AMOUNT && $quoteTotal <= PaymentTerms::PAY_LATER_MAX_AMOUNT;
    }
}
