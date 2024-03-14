<?php

namespace Iwoca\Iwocapay\Cron;

use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\IwocaClientFactory;
use Iwoca\Iwocapay\Controller\Process\Callback;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\GuzzleException;

class ReconcileLostPayments
{
    protected OrderFactory $orderFactory;
    protected IwocaClientFactory $iwocaClientFactory;
    private Config $config;
    private Json $jsonSerializer;
    protected CollectionFactory $orderCollectionFactory;
    protected LoggerInterface $logger;

    public function __construct(
        CollectionFactory  $orderCollectionFactory,
        LoggerInterface    $logger,
        OrderFactory       $orderFactory,
        IwocaClientFactory $iwocaClientFactory,
        Config             $config,
        Json               $jsonSerializer
    )
    {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->iwocaClientFactory = $iwocaClientFactory;
        $this->config = $config;
        $this->jsonSerializer = $jsonSerializer;
    }

    protected function getPendingIwocaPayOrders(): array
    {
        $orders = [];
        foreach ($this->orderFactory->create()->getCollection() as $order) {
            if ($order->getPayment()->getMethod() === 'iwocapay' && $order->getStatus() === 'pending_payment') {
                $customerId = $order->getCustomerId();
                $orders[$customerId][] = $order;
            }
        }
        return $orders;
    }

    protected function findUUIDInOrderComments($order)
    {
        foreach ($order->getStatusHistoryCollection() as $comment) {
            if (preg_match('/"\b[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-4[A-Fa-f0-9]{3}-[89ABab][A-Fa-f0-9]{3}-[A-Fa-f0-9]{12}\b"/', $comment->getComment(), $matches)) {
                return str_replace('"', '', $matches[0]);
            }
        }
        return null;
    }

    protected function getLatestStatusForIwocaPayOrder($order): ?string
    {
        $extractedOrderID = $this->findUUIDInOrderComments($order);
        if ($extractedOrderID === null) {
            $this->logger->error("ReconcileLostPayments: Unable to retrieve order id for order {$order->getIncrementId()}.");
            return null;
        }

        try {
            $rawResponse = $this->iwocaClientFactory->create()->get(
                $this->config->getApiEndpoint(
                    Config::CONFIG_TYPE_GET_ORDER_ENDPOINT,
                    [':' . Callback::IWOCA_ORDER_ID_PARAM => $extractedOrderID]
                )
            );
        } catch (GuzzleException|LocalizedException $e) {
            $this->logger->info(
                sprintf(
                    'ReconcileLostPayments: Error occurred while %s. Received exception %s',
                    $e instanceof GuzzleException ? 'retrieving Iwoca order response for order with Iwoca ID ' . $extractedOrderID : 'getting API endpoint of type "' . Config::CONFIG_TYPE_GET_ORDER_ENDPOINT . '"',
                    $e->getMessage()
                )
            );
            return null;
        }

        $responseJson = $rawResponse->getBody()->getContents();
        $responseData = $this->jsonSerializer->unserialize($responseJson);
        return $responseData["data"]["status"] ?? null;
    }

    private function markOrderAsProcessed($order)
    {
        $this->logger->info("ReconcileLostPayments: iwocaPay order with id {$order->getIncrementId()} has been completed and is being marked as processed.");
        $order->setState(Order::STATE_PROCESSING)
            ->setStatus(Order::STATE_PROCESSING)
            ->addStatusToHistory(Order::STATE_PROCESSING, 'Order set to processing by the ReconcileLostPayments CRON job.', true)
            ->save();
    }

    /**
     * We only mark orders as successful here, we don't mark as cancelled if Declined as they may have checked out
     * via a different method, and we want to avoid cancelling the order. They will be cancelled by the clean-up task
     * anyway if they did not continue.
     */
    public function execute()
    {
        try {
            $orders = $this->getPendingIwocaPayOrders();
            foreach ($orders as $customerId => $customerOrders) {
                foreach ($customerOrders as $order) {
                    $latestStatus = $this->getLatestStatusForIwocaPayOrder($order);
                    if ($latestStatus === "SUCCESSFUL") {
                        $this->markOrderAsProcessed($order);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('ReconcileLostPayments: Error in ReconcileLostPayments CRON job' . $e->getMessage());
        }
    }
}
