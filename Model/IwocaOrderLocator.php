<?php

declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\Payment\CollectionFactory as PaymentCollectionFactory;

/**
 * Resolves a Magento order from an iwoca order UUID with no dependency on the
 * checkout session. Shared by the callback controller and the success-page
 * session-rehydration plugin.
 */
class IwocaOrderLocator
{
    private PaymentCollectionFactory $paymentCollectionFactory;
    private OrderFactory $orderFactory;

    public function __construct(
        PaymentCollectionFactory $paymentCollectionFactory,
        OrderFactory $orderFactory
    ) {
        $this->paymentCollectionFactory = $paymentCollectionFactory;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Find an order by the iwoca order ID stored in payment additional information.
     */
    public function findByIwocaOrderId(string $iwocaOrderId): ?Order
    {
        $paymentCollection = $this->paymentCollectionFactory->create();
        $paymentCollection->addFieldToFilter('additional_information', ['like' => '%' . $this->escapeForLike($iwocaOrderId) . '%']);

        foreach ($paymentCollection as $payment) {
            $additionalInfo = $payment->getAdditionalInformation();
            if (isset($additionalInfo['iwocapay_order_id']) && $additionalInfo['iwocapay_order_id'] === $iwocaOrderId) {
                $order = $this->orderFactory->create();
                $order->load($payment->getParentId());
                return $order;
            }
        }

        return null;
    }

    private function escapeForLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
