<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Serialize\Serializer\Json;

class PaymentTerms implements OptionSourceInterface
{

    public const PAY_NOW_PAY_LATER = ['PAY_LATER', 'PAY_NOW'];
    public const PAY_LATER = ['PAY_LATER'];
    public const PAY_NOW = ['PAY_NOW'];
    public const PAY_LATER_MIN_AMOUNT = 150;
    public const PAY_LATER_MAX_AMOUNT = 30000;

    /**
     * @var Json
     */
    private Json $jsonSerializer;

    public function __construct(Json $jsonSerializer)
    {
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => $this->jsonSerializer->serialize(self::PAY_NOW_PAY_LATER),
                'label' => __('Pay Later & Pay Now')
            ],
            [
                'value' => $this->jsonSerializer->serialize(self::PAY_LATER),
                'label' => __('Pay Later Only')
            ]
        ];
    }
}

