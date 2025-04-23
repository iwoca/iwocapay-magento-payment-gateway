<?php

namespace Iwoca\Iwocapay\Controller\Process;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class BlockIwocaEmail implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $paymentMethod = $order->getPayment()->getMethod();
        if ($paymentMethod == 'iwocapay' || $paymentMethod == 'iwocapay_paylater' || $paymentMethod == 'iwocapay_paynow') {
            $order->setCanSendNewEmailFlag(false)->addCommentToStatusHistory(__('iwocaPay: Disabling send email'));
        }
    }
}
