<?php

namespace Iwoca\Iwocapay\Cron;

use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\IwocaClientFactory;
use Iwoca\Iwocapay\Controller\Process\Callback;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Sales\Model\Order;

/**
 */
class CancelAbandonedOrders
{
    /**
     * @var OrderFactory
     */
    protected $orderFactory;
    /**
     * @var IwocaClientFactory
     */
    protected $iwocaClientFactory;
    /**
     * @var Config
     */
    private $config;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected $MARKED_FOR_CANCELLATION_ID = "Marked for cancellation in iwocaPay.";
    protected $CANCELLED_ID = "Cancelled in iwocaPay.";


    public function __construct(
        LoggerInterface    $logger,
        OrderFactory       $orderFactory,
        IwocaClientFactory $iwocaClientFactory,
        Config             $config,
    ) {
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->iwocaClientFactory = $iwocaClientFactory;
        $this->config = $config;
    }

    protected function getCancelledIwocaPayOrders(): OrderCollection
    {
        return $this->orderFactory->create()->getCollection()
            ->join(
                ['payment' => 'sales_order_payment'],
                'main_table.entity_id = payment.parent_id',
                ['method']
            )
            ->addFieldToFilter('payment.method', ['in' => ['iwocapay', 'iwocapay_paylater', 'iwocapay_paynow']])
            ->addFieldToFilter('main_table.status', Order::STATE_CANCELED);
    }

    protected function findUUIDInOrderComments($order): ?string
    {
        foreach ($order->getStatusHistoryCollection() as $comment) {
            if (preg_match('/"\b[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-4[A-Fa-f0-9]{3}-[89ABab][A-Fa-f0-9]{3}-[A-Fa-f0-9]{12}\b"/', $comment->getComment(), $matches)) {
                return str_replace('"', '', $matches[0]);
            }
        }
        return null;
    }

    protected function isOrderMarkedForCancellation($order): bool
    {
        foreach ($order->getStatusHistoryCollection() as $comment) {
            if ($comment->getComment() === null) continue;

            if (strpos($comment->getComment(), $this->MARKED_FOR_CANCELLATION_ID) === false) {
                continue;
            }

            if (strpos($comment->getComment(), $this->CANCELLED_ID) === false) {
                return true;
            }
        }
        return false;
    }

    protected function cancelIwocaPayOrder($order): void
    {
        $extractedOrderID = $this->findUUIDInOrderComments($order);
        if ($extractedOrderID === null) {
            throw new LocalizedException(__('Unable to retrieve order ID.'));
        }

        try {
            $this->iwocaClientFactory->create()->delete(
                $this->config->getApiEndpoint(
                    Config::CONFIG_TYPE_GET_ORDER_ENDPOINT,
                    [':' . Callback::IWOCA_ORDER_ID_PARAM => $extractedOrderID]
                )
            );
        } catch (GuzzleException|LocalizedException $e) {
            throw new LocalizedException(__('An error occurred: %1', $e->getMessage()));
        } catch (\Exception $e) {
            throw new LocalizedException(__('An error occurred: %1', $e->getMessage()));
        }
    }

    private function markOrderAsCancelled($order)
    {
        $this->logger->info("CancelAbandonedOrder: iwocaPay order with id {$order->getId()} has been cancelled");
        $order->addCommentToStatusHistory(__($this->CANCELLED_ID))->save();
    }


    public function execute(): void
    {
        try {
            $orders = $this->getCancelledIwocaPayOrders();
            $this->logger->debug("CancelAbandonedOrder: Found {$orders->count()} orders to cancel");

            foreach ($orders as $order) {
                if (!$this->isOrderMarkedForCancellation($order)) {
                    continue;
                }
                try {
                    $this->cancelIwocaPayOrder($order);
                    $this->markOrderAsCancelled($order);
                } catch (\Exception $e) {
                    $this->logger->error("CancelAbandonedOrder: Unable to cancel order with internal id {$order->getId()}. Error: {$e->getMessage()}");
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('CancelAbandonedOrder: Error in CancelAbandonedOrder CRON job' . $e->getMessage());
        }
    }
}
