<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Observer;

use Iwoca\Iwocapay\Model\Config\Checkout\ConfigProvider;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class DisableOrderEmailBeforeQuoteSubmit implements ObserverInterface
{
    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $event = $observer->getEvent();

        /** @var Quote $quote */
        $quote = $event->getQuote();

        if ($quote->getPayment()->getMethod() !== ConfigProvider::CODE) {
            return $observer;
        }

        /** @var Order $order */
        $order = $event->getOrder();
        $order->setCanSendNewEmailFlag(false);

        return $observer;
    }
}
