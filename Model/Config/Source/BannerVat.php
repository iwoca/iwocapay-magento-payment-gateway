<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BannerVat implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'including', 'label' => __('Yes, prices include VAT')],
            ['value' => 'excluding', 'label' => __('No, prices exclude VAT')],
        ];
    }
}
