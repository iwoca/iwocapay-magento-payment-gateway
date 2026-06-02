<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Mode implements OptionSourceInterface
{

    public const STAGING_MODE = 0;
    public const PROD_MODE = 1;

    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::STAGING_MODE,
                'label' => __('Staging')
            ],
            [
                'value' => self::PROD_MODE,
                'label' => __('Production')
            ]
        ];
    }
}

