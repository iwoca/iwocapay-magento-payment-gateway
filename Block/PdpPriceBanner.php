<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Block;

use Iwoca\Iwocapay\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;

class PdpPriceBanner extends Template
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

        $duration = $this->escapeHtmlAttr($this->config->getPriceBannerDuration());
        $months = self::DURATION_MONTHS[$this->config->getPriceBannerDuration()] ?? 3;
        $theme = $this->escapeHtmlAttr($this->config->getPriceBannerTheme());

        return '<iwocapay-price-calculator-pdp-banner duration="' . $duration . '" theme="' . $theme . '" data-amount="' . $price . '" data-months="' . $months . '"></iwocapay-price-calculator-pdp-banner>';
    }
}
