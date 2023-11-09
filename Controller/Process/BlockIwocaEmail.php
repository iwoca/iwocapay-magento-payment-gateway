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
    if ($paymentMethod == 'iwocapay') {
      $order->setCanSendNewEmailFlag(false)->addCommentToStatusHistory(__('iwocaPay: Disabling send email'));
    }
  }
}