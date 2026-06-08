<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BannerDuration implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '30_days', 'label' => __('30 days')],
            ['value' => '30_days_and_3_months', 'label' => __('30 days & 3 months')],
            ['value' => '3_months', 'label' => __('3 months')],
            ['value' => '12_months', 'label' => __('12 months')],
            ['value' => '3_and_12_months', 'label' => __('3 or 12 months')],
            ['value' => '1_3_and_12_months', 'label' => __('1, 3 or 12 months')],
        ];
    }
}
