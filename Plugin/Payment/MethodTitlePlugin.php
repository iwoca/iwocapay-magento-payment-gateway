<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Plugin\Payment;

use Magento\Payment\Model\Method\Adapter;
use Iwoca\Iwocapay\Model\Config;

class MethodTitlePlugin
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function afterGetTitle(Adapter $subject, $result)
    {
        $code = $subject->getCode();
        return $this->config->getTitle($code) ?: 'iwocaPay';
    }
}
