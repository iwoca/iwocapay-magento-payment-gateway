<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Block;

use Iwoca\Iwocapay\Model\Config\Checkout\ConfigProviderConfigProvider;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Block\Form as PaymentForm;

class Form extends PaymentForm
{
    public function __construct(Template\Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

}
