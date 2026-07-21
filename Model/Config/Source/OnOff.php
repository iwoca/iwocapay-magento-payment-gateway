<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class OnOff implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '1', 'label' => __('On')],
            ['value' => '0', 'label' => __('Off')],
        ];
    }
}
