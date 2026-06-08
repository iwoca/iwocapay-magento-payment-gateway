<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BannerTheme implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'dark', 'label' => __('Dark')],
            ['value' => 'light', 'label' => __('Light')],
        ];
    }
}
