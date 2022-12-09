<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

use Iwoca\Iwocapay\Model\Config\Checkout\ConfigProvider;
use Iwoca\Iwocapay\Model\Config\Source\Mode;
use Iwoca\Iwocapay\Model\Config\Source\PaymentTerms;
use Magento\Config\Model\Config\Backend\Admin\Custom as AdminConfig;
use Magento\Directory\Model\Currency;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Config\Config as GatewayConfig;
use Magento\Store\Model\ScopeInterface;

class Config
{

    public const XML_CONFIG_PATH_ACTIVE = 'payment/iwocapay/active';
    public const XML_CONFIG_PATH_SELLER_ACCESS_TOKEN = 'payment/iwocapay/seller_access_token';
    public const XML_CONFIG_PATH_SELLER_ID = 'payment/iwocapay/seller_id';
    public const XML_CONFIG_PATH_MODE = 'payment/iwocapay/mode';
    public const XML_CONFIG_PATH_ALLOWED_PAYMENT_TERMS = 'payment/iwocapay/allowed_payment_terms';
    public const XML_CONFIG_PATH_TITLE = 'payment/iwocapay/title';
    public const XML_CONFIG_PATH_DEBUG_MODE = 'payment/iwocapay/debug_mode';
    public const XML_PATH_SOURCE = 'payment/iwocapay/source';
    public const XML_PATH_REDIRECT_PATH = 'payment/iwocapay/redirect_path';
    public const XML_CONFIG_PATH_CURRENCY = 'payment/iwocapay/currency';
    public const XML_CONFIG_PATH_ALLOW_SPECIFIC = 'payment/iwocapay/allowspecific';
    public const XML_CONFIG_PATH_SPECIFIC_COUNTRIES = 'payment/iwocapay/specificcountries';
    public const XML_CONFIG_PATH_ALLOWED_CURRENCIES = 'payment/iwocapay/allowed_currencies';
    public const XML_CONFIG_PATH_STAGING_BASE_URL = 'payment/iwocapay/staging_base_url';
    public const XML_CONFIG_PATH_PROD_BASE_URL = 'payment/iwocapay/prod_base_url';
    public const XML_CONFIG_PATH_API_BASE_PATH = 'payment/iwocapay/api_base_path';
    public const XML_CONFIG_PATH_API_PATH_CREATE_ORDER = 'payment/iwocapay/api_path_create_order';
    public const XML_CONFIG_PATH_API_PATH_GET_ORDER = 'payment/iwocapay/api_path_get_order';

    public const CONFIG_TYPE_CREATE_ORDER_ENDPOINT = 'create-order';
    public const CONFIG_TYPE_GET_ORDER_ENDPOINT = 'get-order';
    public const ENDPOINT_CONFIG_MAPPING = [
        self::CONFIG_TYPE_CREATE_ORDER_ENDPOINT => self::XML_CONFIG_PATH_API_PATH_CREATE_ORDER,
        self::CONFIG_TYPE_GET_ORDER_ENDPOINT => self::XML_CONFIG_PATH_API_PATH_GET_ORDER
    ];

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var Json
     */
    private Json $jsonSerializer;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $jsonSerializer
    ){
        $this->scopeConfig = $scopeConfig;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        // Check if the payment method is enabled
        if (!$this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_ACTIVE, ScopeInterface::SCOPE_WEBSITE)) {
            return false;
        }

        // Check if the currency is allowed to be used for this payment method.
        if (!in_array($this->getCurrency(), $this->getAllowedCurrencies())) {
            return false;
        }

        // Check if the required config is set
        if (!$this->getSellerId() || !$this->getSellerAccessToken()) {
            return false;
        }
         return true;
    }

    /**
     * @return string
     */
    public function getSellerAccessToken(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_CONFIG_PATH_SELLER_ACCESS_TOKEN, ScopeInterface::SCOPE_WEBSITE);
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
     * @return array
     */
    public function getAllowedPaymentTerms(): array
    {
        $allowedPaymentTerms = $this->scopeConfig->getValue(self::XML_CONFIG_PATH_ALLOWED_PAYMENT_TERMS, ScopeInterface::SCOPE_WEBSITE);

        if ($allowedPaymentTerms) {
            $allowedPaymentTerms = $this->jsonSerializer->unserialize($allowedPaymentTerms);
        } else {
            $allowedPaymentTerms = PaymentTerms::PAY_NOW_PAY_LATER;
        }

        return $allowedPaymentTerms;
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
    public function getSource(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_SOURCE);
    }

    /**
     * @return string
     */
    public function getRedirectPath(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_REDIRECT_PATH);
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
     * @return array
     */
    public function getAllowedCurrencies(): array
    {
        $allowedCurrencies = $this->scopeConfig->getValue(self::XML_CONFIG_PATH_ALLOWED_CURRENCIES, ScopeInterface::SCOPE_WEBSITE);

        if ($allowedCurrencies) {
            $allowedCurrencies = $this->jsonSerializer->unserialize($allowedCurrencies);
        } else {
            $allowedCurrencies = ['GBP'];
        }

        return $allowedCurrencies;
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        if ($this->getMode() === Mode::STAGING_MODE) {
            return (string) $this->scopeConfig->getValue(self::XML_CONFIG_PATH_STAGING_BASE_URL);
        }

        return (string) $this->scopeConfig->getValue(self::XML_CONFIG_PATH_PROD_BASE_URL);
    }

    /**
     * @return string
     */
    public function getApiBasePath(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_CONFIG_PATH_API_BASE_PATH);
    }

    /**
     * @return string
     */
    public function getApiBaseUrl(): string
    {
        $baseUrl = rtrim($this->getBaseUrl(), '/');
        $basePath = trim($this->getApiBasePath(), '/');
        return $baseUrl . '/' . $basePath;
    }

    /**
     * @param string $type
     * @param array $replacementData
     * @return string
     * @throws LocalizedException
     */
    public function getApiEndpoint(string $type, array $replacementData = []): string
    {
        if (!isset(self::ENDPOINT_CONFIG_MAPPING[$type])) {
            throw new LocalizedException(__('Unknown endpoint type "%1" requested', $type));
        }

        $replacementData = array_merge([':sellerId' => $this->getSellerId()], $replacementData);

        $apiPath = trim((string) $this->scopeConfig->getValue(self::ENDPOINT_CONFIG_MAPPING[$type]), '/');
        $matches = [];
        if (preg_match('~(\:\w+)~', $apiPath, $matches)) {
            $matches = array_unique($matches);
            foreach ($matches as $match) {
                if (isset($replacementData[$match])) {
                    $apiPath = str_replace($match, $replacementData[$match], $apiPath);
                }
            }
        }

        return $this->getApiBaseUrl() . '/' . $apiPath . '/';
    }

    /**
     * @param string $field
     * @param int|null $storeId
     * @return mixed
     */
    public function getPaymentConfig(string $field, ?int $storeId = null): mixed
    {
        $path = sprintf(self::DEFAULT_PATH_PATTERN, 'iwoca_iwocapay', $field);
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
