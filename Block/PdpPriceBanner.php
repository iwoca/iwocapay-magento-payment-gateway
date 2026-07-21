<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Block;

use Iwoca\Iwocapay\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;

class PdpPriceBanner extends Template
{
    private Config $config;
    private Registry $registry;

    public function __construct(
        Template\Context $context,
        Config $config,
        Registry $registry,
        array $data = []
    ) {
        $this->config = $config;
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    private function getProduct(): ?Product
    {
        return $this->registry->registry('product');
    }

    protected function _toHtml(): string
    {
        if (!$this->config->isPriceBannerEnabled()) {
            return '';
        }

        $product = $this->getProduct();
        if (!$product) {
            return '';
        }

        $price = (float) $product->getFinalPrice();
        if ($price <= 0) {
            return '';
        }

        $term = $this->config->getBestBannerTerm();
        if (!$term) {
            return '';
        }

        // The price reflects the best (longest) term, but the copy lists every
        // enabled term via the combined duration enum.
        $duration = $this->escapeHtmlAttr($this->config->getBannerDurationEnum() ?? $term['duration']);
        $months = (int) $term['months'];
        $interest = $this->escapeHtmlAttr(str_replace('_', '-', $term['interest']));
        $vat = $this->escapeHtmlAttr($this->config->getPriceBannerVat());
        $theme = $this->escapeHtmlAttr($this->config->getPriceBannerTheme());

        return '<iwocapay-price-calculator-pdp-banner duration="' . $duration . '" theme="' . $theme . '" data-amount="' . $price . '" data-months="' . $months . '" data-interest="' . $interest . '" data-vat="' . $vat . '"></iwocapay-price-calculator-pdp-banner>';
    }
}
