<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BannerPricing implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'seller_pays', 'label' => __('Free for your customers (interest free for customer)')],
            ['value' => 'buyer_pays', 'label' => __('Free for you (interest bearing for customer)')],
        ];
    }
}
