<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BannerPricing implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'seller_pays', 'label' => __('Seller pays (interest free for buyer)')],
            ['value' => 'buyer_pays', 'label' => __('Buyer pays (interest bearing)')],
        ];
    }
}
