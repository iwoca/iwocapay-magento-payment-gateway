<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

use Iwoca\Iwocapay\Model\Config\Checkout\ConfigProvider;
use Iwoca\Iwocapay\Model\Config\Source\Mode;
use Magento\Config\Model\Config\Backend\Admin\Custom as AdminConfig;
use Magento\Directory\Model\Currency;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Config\Config as GatewayConfig;
use Magento\Store\Model\ScopeInterface;

class Config
{

    public const XML_CONFIG_PATH_ACTIVE = 'payment/iwoca_iwocapay/active';
    public const XML_CONFIG_PATH_SELLER_ACCESS_KEY = 'payment/iwoca_iwocapay/seller_access_key';
    public const XML_CONFIG_PATH_SELLER_ID = 'payment/iwoca_iwocapay/seller_id';
    public const XML_CONFIG_PATH_MODE = 'payment/iwoca_iwocapay/mode';
    public const XML_CONFIG_PATH_TITLE = 'payment/iwoca_iwocapay/title';
    public const XML_CONFIG_PATH_DEBUG_MODE = 'payment/iwoca_iwocapay/debug_mode';
    public const XML_CONFIG_PATH_CURRENCY = 'payment/iwoca_iwocapay/currency';
    public const XML_CONFIG_PATH_ALLOW_SPECIFIC = 'payment/iwoca_iwocapay/allowspecific';
    public const XML_CONFIG_PATH_SPECIFIC_COUNTRIES = 'payment/iwoca_iwocapay/specificcountries';
    public const XML_CONFIG_PATH_STAGING_API_BASE_URL = 'payment/iwoca_iwocapay/staging_api_base_url';
    public const XML_CONFIG_PATH_PROD_API_BASE_URL = 'payment/iwoca_iwocapay/prod_api_base_url';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_ACTIVE, ScopeInterface::SCOPE_WEBSITE) &&
            $this->getSellerId();
    }

    /**
     * @return string
     */
    public function getSellerAccessKey(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_CONFIG_PATH_SELLER_ACCESS_KEY, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * @return string
     */
    public function getSellerId(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_CONFIG_PATH_SELLER_ID, ScopeInterface::SCOPE_WEBSITE);
    }


    /**
     * @return string
     */
    public function getMode(): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_CONFIG_PATH_MODE, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_CONFIG_PATH_TITLE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return bool
     */
    public function isDebugModeEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_DEBUG_MODE);
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return (string) $this->scopeConfig->getValue(Currency::XML_PATH_CURRENCY_BASE, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * @return bool
     */
    public function isAllowSpecific(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_ALLOW_SPECIFIC, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * @return array
     */
    public function getSpecificCountries(): array
    {
        return $this->scopeConfig->getValue(self::XML_CONFIG_PATH_SPECIFIC_COUNTRIES, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * @return string
     */
    public function getApiBaseUrl(): string
    {
        if ($this->getMode() === Mode::MODE_STAGING) {
            return (string) $this->scopeConfig->getValue(self::XML_CONFIG_PATH_STAGING_API_BASE_URL);
        }

        return (string) $this->scopeConfig->getValue(self::XML_CONFIG_PATH_PROD_API_BASE_URL);
    }

    /**
     * @param string $field
     * @param int|null $storeId
     * @return mixed
     */
    public function getPaymentConfig(string $field, ?int $storeId = null): mixed
    {
        $path = sprintf(GatewayConfig::DEFAULT_PATH_PATTERN, ConfigProvider::CODE, $field);
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
