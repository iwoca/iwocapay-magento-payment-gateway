<?php

namespace Iwoca\Iwocapay\Cron;

use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\IwocaClientFactory;
use Iwoca\Iwocapay\Controller\Process\Callback;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\GuzzleException;

use Magento\Framework\App\ResourceConnection;

/**
 * This class attempts to reconcile payments that have dropped off early e.g. they
 * fell out of the flow after draw-down but before redirecting back to the merchant.
 *
 * It also prolongs iwocaPay orders so that they aren't automatically cleared after
 * a user set timeout. Instead, 48 hours is used.
 */
class ReconcileLostPayments
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
     * @var Json
     */
    private $jsonSerializer;
    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    public function __construct(
        CollectionFactory  $orderCollectionFactory,
        LoggerInterface    $logger,
        OrderFactory       $orderFactory,
        IwocaClientFactory $iwocaClientFactory,
        Config             $config,
        Json               $jsonSerializer,
        TimezoneInterface  $timezone,
        ResourceConnection $resourceConnection
    )
    {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->iwocaClientFactory = $iwocaClientFactory;
        $this->config = $config;
        $this->jsonSerializer = $jsonSerializer;
        $this->timezone = $timezone;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Returns all orders which were completed using iwocaPay and are in the "pending payment" state
     *
     * @return array
     */
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

    /**
     * Returns an order_id for an order. This is parsed from the comment history as there is no place to store
     * it within Magento. It may be null if no order_id is found.
     *
     * @param $order
     * @return string|null
     */
    protected function findUUIDInOrderComments($order): ?string
    {
        foreach ($order->getStatusHistoryCollection() as $comment) {
            if (preg_match('/"\b[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-4[A-Fa-f0-9]{3}-[89ABab][A-Fa-f0-9]{3}-[A-Fa-f0-9]{12}\b"/', $comment->getComment(), $matches)) {
                return str_replace('"', '', $matches[0]);
            }
        }
        return null;
    }

    /**
     * Returns the latest status for an iwocaPay order by querying the Ecomm API.
     * Response might be null if an error is returned.
     *
     * @param $order
     * @return string|null
     */
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

    /**
     * Marks an order as processed, adding a status to history noting that this was done by the
     * recovery Cron job
     *
     * @param $order
     * @return void
     */
    private function markOrderAsProcessed($order)
    {
        $this->logger->info("ReconcileLostPayments: iwocaPay order with id {$order->getIncrementId()} has been completed and is being marked as processed.");
        $order->setState(Order::STATE_PROCESSING)
            ->setStatus(Order::STATE_PROCESSING)
            ->addStatusToHistory(Order::STATE_PROCESSING, 'Order set to processing by the ReconcileLostPayments CRON job.', true)
            ->save();
    }


    /**
     * Returns if the order is within 48 hours of creation.
     *
     * @param $order
     * @return bool
     */
    public function createdWithinThreshold($order): bool
    {
        $createdAtTimestamp = strtotime($order->getCreatedAt());
        $differenceInSeconds = $this->timezone->scopeTimeStamp() - $createdAtTimestamp;
        return $differenceInSeconds < (48 * 3600);
    }

    /**
     * If the order is less than 48 hours old prolong the updated_at date to prevent clean-up.
     *
     * @param $order
     * @return void
     */
    private function prolongOrKillOrderLife($order): void
    {
        if (!$this->createdWithinThreshold($order)) return;
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $connection->getTableName('sales_order');
            $connection->update(
                $tableName,
                ['updated_at' => date('Y-m-d H:i:s')],
                ['entity_id = ?' => $order->getId()]
            );
        } catch (\Exception $e) {
            $this->logger->error("ReconcileLostPayments: Error updating updatedAt timestamp: " . $e->getMessage());
        }
    }

    /**
     * If an iwocaPay pending order is marked as:
     * - "SUCCESSFUL"
     *      - We mark it as processed.
     * - "PENDING"
     *      - We alter the updated_at entry so that it is not killed by Magento\Sales\Model\CronJob\CleanExpiredOrders
     *        this is done by consistently pushing the last modified date to present time until it has been 48 hours
     *        since the order creation. At this point we no longer bump the modified date, allowing it to be cleaned up
     *        as expected.
     * - "UNSUCCESSFUL"
     *      - We do nothing, meaning it is picked up by the CleanExpiredOrders Cron job at the regular time and
     *        cancelled.
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $orders = $this->getPendingIwocaPayOrders();
            foreach ($orders as $customerId => $customerOrders) {
                foreach ($customerOrders as $order) {
                    $latestStatus = $this->getLatestStatusForIwocaPayOrder($order);
                    if ($latestStatus === "SUCCESSFUL") {
                        $this->markOrderAsProcessed($order);
                    }
                    if ($latestStatus === "PENDING") {
                        $this->prolongOrKillOrderLife($order);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('ReconcileLostPayments: Error in ReconcileLostPayments CRON job' . $e->getMessage());
        }
    }
}
