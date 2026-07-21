<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Plugin\Catalog;

use Iwoca\Iwocapay\Model\Config;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Model\Product;
use Magento\Framework\Escaper;

class ProductListPricePlugin
{
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

        $term = $this->config->getBestBannerTerm();
        if (!$term) {
            return $result;
        }

        // The price reflects the best (longest) term, but the copy lists every
        // enabled term via the combined duration enum.
        $duration = $this->escaper->escapeHtmlAttr($this->config->getBannerDurationEnum() ?? $term['duration']);
        $months = (int) $term['months'];
        $interest = $this->escaper->escapeHtmlAttr(str_replace('_', '-', $term['interest']));
        $vat = $this->escaper->escapeHtmlAttr($this->config->getPriceBannerVat());
        $theme = $this->escaper->escapeHtmlAttr($this->config->getPriceBannerTheme());

        return $result . '<iwocapay-price-calculator-plp-banner duration="' . $duration . '" theme="' . $theme . '" data-amount="' . $price . '" data-months="' . $months . '" data-interest="' . $interest . '" data-vat="' . $vat . '"></iwocapay-price-calculator-plp-banner>';
    }
}
