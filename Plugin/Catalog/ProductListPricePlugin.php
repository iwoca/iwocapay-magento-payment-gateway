<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Plugin\Catalog;

use Iwoca\Iwocapay\Model\Config;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Model\Product;
use Magento\Framework\Escaper;

class ProductListPricePlugin
{
    private const DURATION_MONTHS = [
        '30_days' => 1,
        '30_days_and_3_months' => 3,
        '3_months' => 3,
        '12_months' => 12,
        '3_and_12_months' => 3,
        '1_3_and_12_months' => 12,
    ];

    private Config $config;
    private Escaper $escaper;

    public function __construct(Config $config, Escaper $escaper)
    {
        $this->config = $config;
        $this->escaper = $escaper;
    }

    public function afterGetProductPrice(ListProduct $subject, string $result, Product $product): string
    {
        if (!$this->config->isPriceBannerEnabled()) {
            return $result;
        }

        $price = (float) $product->getFinalPrice();
        if ($price <= 0) {
            return $result;
        }

        $duration = $this->escaper->escapeHtmlAttr($this->config->getPriceBannerDuration());
        $months = self::DURATION_MONTHS[$this->config->getPriceBannerDuration()] ?? 3;
        $theme = $this->escaper->escapeHtmlAttr($this->config->getPriceBannerTheme());

        return $result . '<iwocapay-price-calculator-plp-banner duration="' . $duration . '" theme="' . $theme . '" data-amount="' . $price . '" data-months="' . $months . '"></iwocapay-price-calculator-plp-banner>';
    }
}
