<?php

namespace Iwoca\Iwocapay\Cron;

use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\IwocaClientFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\LocalizedException;

class ReconcileLostPayments
{
    public const IWOCA_ORDER_ID_PARAM = 'orderId';
    protected $orderFactory;
    protected IwocaClientFactory $iwocaClientFactory;
    private Config $config;
    private Json $jsonSerializer;

    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * YourCustomJob constructor.
     *
     * @param CollectionFactory $orderCollectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory $orderCollectionFactory,
        LoggerInterface $logger,
        OrderFactory $orderFactory,
        IwocaClientFactory $iwocaClientFactory,
        Config $config,
        Json $jsonSerializer
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->iwocaClientFactory = $iwocaClientFactory;
        $this->config = $config;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Get all the payment pending orders which are iwocaPay.
     */
    protected function getPendingIwocaPayOrders(): array
    {
        $ordersCollection = $this->orderFactory->create()->getCollection();
        $orders = [];
        foreach ($ordersCollection as $order) {
            if ($order->getPayment()->getMethod() === 'iwocapay' && $order->getStatus() === 'pending_payment') {
                $customerId = $order->getCustomerId();
                if (!isset($orders[$customerId])) {
                    $orders[$customerId] = [];
                }
                $orders[$customerId][] = $order;
            }
        }
        return $orders;
    }


    /**
     * OrderIds are not stored on the Order, but are contained within the order history.
     * So we parse it out of the comment and return it sanitised here.
     */
    protected function findUUIDInOrderComments($order) {
        $orderHistory = $order->getStatusHistoryCollection();
        foreach ($orderHistory as $comment) {
            $commentText = $comment->getComment();
            preg_match('/"\b[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-4[A-Fa-f0-9]{3}-[89ABab][A-Fa-f0-9]{3}-[A-Fa-f0-9]{12}\b"/', $commentText, $matches);

            if (!empty($matches)) {
                return str_replace('"', '', $matches[0]);
            }
        }

        return null;
    }

    /**
     * Query the ecommerce api to check the status of the order to see if we need to mark
     * it as complete/ rejected.
     */
    protected function getLatestStatusForIwocaPayOrder($order) {
        $extractedOrderID = $this->findUUIDInOrderComments($order);
        if ($extractedOrderID === null) {
            return $this->logger->error(
               "iwp: Unable to retrieve order id for order."
            );
        }

        $iwocaClient = $this->iwocaClientFactory->create();

        try {
            $rawResponse = $iwocaClient->get(
                $this->config->getApiEndpoint(
                    Config::CONFIG_TYPE_GET_ORDER_ENDPOINT,
                    [':' . self::IWOCA_ORDER_ID_PARAM => $extractedOrderID]
                )
            );
        } catch (GuzzleException $e) {
            $this->logger->info(
                sprintf(
                    'iwp: Error occurred while retrieving Iwoca order response for order with Iwoca ID %s. Received exception %s',
                    $extractedOrderID,
                    $e->getMessage()
                )
            );
            throw $e;
        } catch (LocalizedException $e) {
            $this->logger->info(
                sprintf(
                    'iwp: Error occurred while getting API endpoint of type "%s". Exception: %s',
                    Config::CONFIG_TYPE_GET_ORDER_ENDPOINT,
                    $e->getMessage()
                )
            );
            throw $e;
        }

        $responseJson = $rawResponse->getBody()->getContents();
        $responseData = $this->jsonSerializer->unserialize($responseJson);
        return $responseData["data"]["status"];
    }


    public function execute()
    {
        $this->logger->info("iwp: ---");
        $this->logger->info("iwp: ---");
        $this->logger->info("iwp: Will reconcile any orders that have not already been marked as complete");

        try {
            // Retrieve all pending orders
            $orders = $this->getPendingIwocaPayOrders();
            foreach ($orders as $customerId => $customerOrders) {
                foreach ($customerOrders as $order) {
                    $reference_id = $order->getIncrementId();
                    $latestOrderId = $this->getLatestStatusForIwocaPayOrder($order);
                    if ($latestOrderId !== "COMPLETED") {
                        $this->logger->debug("iwp: Order with id {$reference_id} has not been completed.");

                    }else {
                        $this->logger->debug("iwp: Order with id {$reference_id} has been completed and we should mark as done...");
                        // ... MARK AS COMPLETED HERE
                    }
                }
            }


            $this->logger->info('iwp: Finished looking at pending payments');
            $this->logger->info('iwp: ---');
        } catch (\Exception $e) {
            $this->logger->error('Error in YourCustomJob: ' . $e->getMessage());
        }
    }
}
