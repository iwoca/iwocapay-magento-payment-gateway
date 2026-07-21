<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

use Iwoca\Iwocapay\Model\Config\Checkout\ConfigProvider;
use Iwoca\Iwocapay\Model\Config\Source\Mode;
use Iwoca\Iwocapay\Model\Config\Source\PaymentTerms;
use Magento\Directory\Model\Currency;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
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
    public const XML_CONFIG_PATH_TITLE = 'payment/%s/title';
    public const XML_CONFIG_PATH_SUBTITLE = 'payment/%s/subtitle';
    public const XML_CONFIG_PATH_CALL_TO_ACTION = 'payment/%s/call_to_action';
    public const XML_CONFIG_PATH_ALLOWED_PAYMENT_TERMS = 'payment/iwocapay/allowed_payment_terms'; // used for settings
    public const XML_CONFIG_PATH_DEBUG_MODE = 'payment/iwocapay/debug_mode';
    public const XML_PATH_SOURCE = 'payment/iwocapay/source';
    public const XML_PATH_REDIRECT_PATH = 'payment/iwocapay/redirect_path';
    public const XML_CONFIG_PATH_CURRENCY = 'payment/iwocapay/currency';
    public const XML_CONFIG_PATH_ALLOW_SPECIFIC = 'payment/iwocapay/allowspecific';
    public const XML_CONFIG_PATH_SPECIFIC_COUNTRIES = 'payment/iwocapay/specificcountries';
    public const XML_CONFIG_PATH_ALLOWED_CURRENCIES = 'payment/iwocapay/allowed_currencies';
    public const XML_CONFIG_PATH_PRICE_BANNER_ENABLED = 'payment/iwocapay/price_banner_enabled';
    public const XML_CONFIG_PATH_PRICE_BANNER_30D_ENABLED = 'payment/iwocapay/price_banner_30d_enabled';
    public const XML_CONFIG_PATH_PRICE_BANNER_30D_INTEREST = 'payment/iwocapay/price_banner_30d_interest';
    public const XML_CONFIG_PATH_PRICE_BANNER_3M_ENABLED = 'payment/iwocapay/price_banner_3m_enabled';
    public const XML_CONFIG_PATH_PRICE_BANNER_3M_INTEREST = 'payment/iwocapay/price_banner_3m_interest';
    public const XML_CONFIG_PATH_PRICE_BANNER_12M_ENABLED = 'payment/iwocapay/price_banner_12m_enabled';
    public const XML_CONFIG_PATH_PRICE_BANNER_12M_INTEREST = 'payment/iwocapay/price_banner_12m_interest';
    public const XML_CONFIG_PATH_PRICE_BANNER_VAT = 'payment/iwocapay/price_banner_vat';
    public const XML_CONFIG_PATH_PRICE_BANNER_THEME = 'payment/iwocapay/price_banner_theme';
    public const XML_CONFIG_PATH_STAGING_BASE_URL = 'payment/iwocapay/staging_base_url';
    public const XML_CONFIG_PATH_PROD_BASE_URL = 'payment/iwocapay/prod_base_url';
    public const XML_CONFIG_PATH_API_BASE_PATH_LENDING = 'payment/iwocapay/api_base_path_lending';
    public const XML_CONFIG_PATH_API_BASE_PATH_IWOCAPAY = 'payment/iwocapay/api_base_path_iwocapay';
    public const XML_CONFIG_PATH_API_PATH_CREATE_ORDER = 'payment/iwocapay/api_path_create_order';
    public const XML_CONFIG_PATH_API_PATH_GET_ORDER = 'payment/iwocapay/api_path_get_order';
    public const XML_CONFIG_PATH_API_PATH_CONNECTION_CHECK = 'payment/iwocapay/api_path_connection_check';

    public const CONFIG_TYPE_CREATE_ORDER_ENDPOINT = 'create-order';
    public const CONFIG_TYPE_GET_ORDER_ENDPOINT = 'get-order';
    public const ENDPOINT_CONFIG_MAPPING = [
        self::CONFIG_TYPE_CREATE_ORDER_ENDPOINT => self::XML_CONFIG_PATH_API_PATH_CREATE_ORDER,
        self::CONFIG_TYPE_GET_ORDER_ENDPOINT => self::XML_CONFIG_PATH_API_PATH_GET_ORDER
    ];
    public const ENDPOINT_BASE_PATH_MAPPING = [
        self::CONFIG_TYPE_CREATE_ORDER_ENDPOINT => self::XML_CONFIG_PATH_API_BASE_PATH_LENDING,
        self::CONFIG_TYPE_GET_ORDER_ENDPOINT => self::XML_CONFIG_PATH_API_BASE_PATH_IWOCAPAY
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
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $jsonSerializer
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json                 $jsonSerializer,
        EncryptorInterface   $encryptor
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->jsonSerializer = $jsonSerializer;
        $this->encryptor = $encryptor;
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
        $encryptedValue = (string)$this->scopeConfig->getValue(self::XML_CONFIG_PATH_SELLER_ACCESS_TOKEN, ScopeInterface::SCOPE_WEBSITE);
        return $encryptedValue ? $this->encryptor->decrypt($encryptedValue) : '';
    }

    /**
     * @return string
     */
    public function getSellerId(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_CONFIG_PATH_SELLER_ID, ScopeInterface::SCOPE_WEBSITE);
    }


    /**
     * @return int
     */
    public function getMode(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_CONFIG_PATH_MODE, ScopeInterface::SCOPE_WEBSITE);
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
     * USED FOR GLOBAL SETTINGS
     *
     *
     * @param string $methodCode
     * @return array
     */
    public function getAllowedPaymentTermOptions(string $methodCode): array
    {
        if ($methodCode === ConfigProvider::CODE_PAY_NOW) {
            return PaymentTerms::PAY_NOW;
        }

        return PaymentTerms::PAY_LATER;
    }

    /**
     * @param string $methodCode
     * @return string
     */
    public function getTitle(string $methodCode): string
    {
        $path = sprintf(self::XML_CONFIG_PATH_TITLE, $methodCode);
        return (string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @param string $methodCode
     * @return string
     */
    public function getSubtitle(string $methodCode): string
    {
        $path = sprintf(self::XML_CONFIG_PATH_SUBTITLE, $methodCode);
        return (string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @param string $methodCode
     * @return string
     */
    public function getCallToAction(string $methodCode): string
    {
        $path = sprintf(self::XML_CONFIG_PATH_CALL_TO_ACTION, $methodCode);
        return (string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * The instalment terms offered on banners, in ascending duration order.
     * `duration` is the web-component enum; `months` feeds the pricing endpoint.
     */
    private const BANNER_TERMS = [
        [
            'key' => '30d',
            'duration' => '30_days',
            'months' => 1,
            'enabledPath' => self::XML_CONFIG_PATH_PRICE_BANNER_30D_ENABLED,
            'interestPath' => self::XML_CONFIG_PATH_PRICE_BANNER_30D_INTEREST,
        ],
        [
            'key' => '3m',
            'duration' => '3_months',
            'months' => 3,
            'enabledPath' => self::XML_CONFIG_PATH_PRICE_BANNER_3M_ENABLED,
            'interestPath' => self::XML_CONFIG_PATH_PRICE_BANNER_3M_INTEREST,
        ],
        [
            'key' => '12m',
            'duration' => '12_months',
            'months' => 12,
            'enabledPath' => self::XML_CONFIG_PATH_PRICE_BANNER_12M_ENABLED,
            'interestPath' => self::XML_CONFIG_PATH_PRICE_BANNER_12M_INTEREST,
        ],
    ];

    public function isPriceBannerEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_PRICE_BANNER_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Enabled instalment terms in ascending duration order. Each entry:
     *   ['key' => '3m', 'duration' => '3_months', 'months' => 3, 'interest' => 'seller_pays']
     *
     * @return array<int, array{key: string, duration: string, months: int, interest: string}>
     */
    public function getEnabledBannerTerms(): array
    {
        $terms = [];
        foreach (self::BANNER_TERMS as $term) {
            if (!$this->scopeConfig->isSetFlag($term['enabledPath'], ScopeInterface::SCOPE_STORE)) {
                continue;
            }
            $terms[] = [
                'key' => $term['key'],
                'duration' => $term['duration'],
                'months' => $term['months'],
                'interest' => (string) ($this->scopeConfig->getValue($term['interestPath'], ScopeInterface::SCOPE_STORE) ?: 'seller_pays'),
            ];
        }
        return $terms;
    }

    /**
     * The single most attractive offer for the PDP/PLP banner: the longest
     * enabled instalment term (lowest monthly figure), falling back to the
     * 30-day offer if no instalment term is enabled. Null if nothing enabled.
     *
     * @return array{key: string, duration: string, months: int, interest: string}|null
     */
    public function getBestBannerTerm(): ?array
    {
        $terms = $this->getEnabledBannerTerms();
        if (!$terms) {
            return null;
        }

        $instalments = array_filter($terms, static fn (array $t): bool => $t['months'] > 1);
        if ($instalments) {
            return end($instalments) ?: null;
        }

        return $terms[0];
    }

    /**
     * The web-component `duration` enum representing the full enabled set, so
     * the PDP/PLP banner lists every offered term ("Pay over 1, 3 or 12
     * monthly instalments from …") while the price is still driven by the best
     * (longest) term via getBestBannerTerm(). Null if nothing is enabled.
     *
     * Note: the component supports a fixed enum set; {30d, 12m} (no 3m) has no
     * combined enum, so it falls back to the 12-month single-term label.
     */
    public function getBannerDurationEnum(): ?string
    {
        $has30d = $this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_PRICE_BANNER_30D_ENABLED, ScopeInterface::SCOPE_STORE);
        $has3m = $this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_PRICE_BANNER_3M_ENABLED, ScopeInterface::SCOPE_STORE);
        $has12m = $this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_PRICE_BANNER_12M_ENABLED, ScopeInterface::SCOPE_STORE);

        return match (true) {
            $has30d && $has3m && $has12m => '1_3_and_12_months',
            $has3m && $has12m => '3_and_12_months',
            $has30d && $has3m => '30_days_and_3_months',
            $has12m => '12_months',
            $has3m => '3_months',
            $has30d => '30_days',
            default => null,
        };
    }

    public function getPriceBannerVat(): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_CONFIG_PATH_PRICE_BANNER_VAT, ScopeInterface::SCOPE_STORE) ?: 'including');
    }

    public function getPriceBannerTheme(): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_CONFIG_PATH_PRICE_BANNER_THEME, ScopeInterface::SCOPE_STORE) ?: 'dark');
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
        return (string)$this->scopeConfig->getValue(self::XML_PATH_SOURCE);
    }

    /**
     * @return string
     */
    public function getRedirectPath(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_REDIRECT_PATH);
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return (string)$this->scopeConfig->getValue(Currency::XML_PATH_CURRENCY_BASE, ScopeInterface::SCOPE_WEBSITE);
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
            return (string)$this->scopeConfig->getValue(self::XML_CONFIG_PATH_STAGING_BASE_URL);
        }

        return (string)$this->scopeConfig->getValue(self::XML_CONFIG_PATH_PROD_BASE_URL);
    }

    /**
     * Resolve the iwoca base URL for an explicit mode.
     *
     * Unlike getBaseUrl(), which reads the stored mode, this takes the mode
     * directly so credential validation can build the URL from the value the
     * seller just submitted (which may differ from what's stored).
     *
     * @param int $mode One of Mode::STAGING_MODE / Mode::PROD_MODE.
     * @return string
     */
    public function getBaseUrlForMode(int $mode): string
    {
        if ($mode === Mode::STAGING_MODE) {
            return (string)$this->scopeConfig->getValue(self::XML_CONFIG_PATH_STAGING_BASE_URL);
        }

        return (string)$this->scopeConfig->getValue(self::XML_CONFIG_PATH_PROD_BASE_URL);
    }

    /**
     * Build the connection_check URL for an explicit seller id and mode.
     *
     * The endpoint returns 200 only when the access token is valid AND belongs
     * to the given seller in that environment, so it is used to verify
     * credentials before they are saved. Built from explicit values (rather
     * than stored config) so it can validate what the seller just submitted.
     *
     * @param string $sellerId
     * @param int $mode One of Mode::STAGING_MODE / Mode::PROD_MODE.
     * @return string
     */
    public function getConnectionCheckUrl(string $sellerId, int $mode): string
    {
        $baseUrl = rtrim($this->getBaseUrlForMode($mode), '/');
        $basePath = trim((string)$this->scopeConfig->getValue(self::XML_CONFIG_PATH_API_BASE_PATH_IWOCAPAY), '/');
        $apiPath = trim((string)$this->scopeConfig->getValue(self::XML_CONFIG_PATH_API_PATH_CONNECTION_CHECK), '/');
        $apiPath = str_replace(':sellerId', rawurlencode($sellerId), $apiPath);

        return $baseUrl . '/' . $basePath . '/' . $apiPath . '/';
    }

    /**
     * @param string $endpointType
     * @return string
     */
    public function getApiBasePath(string $endpointType): string
    {
        $basePathConfig = self::ENDPOINT_BASE_PATH_MAPPING[$endpointType] ?? self::XML_CONFIG_PATH_API_BASE_PATH_LENDING;
        return (string)$this->scopeConfig->getValue($basePathConfig);
    }

    /**
     * @param string $endpointType
     * @return string
     */
    public function getApiBaseUrl(string $endpointType): string
    {
        $baseUrl = rtrim($this->getBaseUrl(), '/');
        $basePath = trim($this->getApiBasePath($endpointType), '/');
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

        $apiPath = trim((string)$this->scopeConfig->getValue(self::ENDPOINT_CONFIG_MAPPING[$type]), '/');
        $matches = [];
        if (preg_match_all('~(\:\w+)~', $apiPath, $matches)) {
            $matches = array_unique($matches[1]);
            foreach ($matches as $match) {
                if (isset($replacementData[$match])) {
                    $apiPath = str_replace($match, $replacementData[$match], $apiPath);
                }
            }
        }

        return $this->getApiBaseUrl($type) . '/' . $apiPath . '/';
    }

    /**
     * @param string $field
     * @param int|null $storeId
     * @return mixed
     */
    public function getPaymentConfig(string $field, ?int $storeId = null)
    {
        $path = sprintf(GatewayConfig::DEFAULT_PATH_PATTERN, 'iwoca_iwocapay', $field);
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
