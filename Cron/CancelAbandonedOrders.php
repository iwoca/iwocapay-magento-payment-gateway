<?php

namespace Iwoca\Iwocapay\Cron;

use Iwoca\Iwocapay\Model\CancelOrderOutcomePolicy;
use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\IntegrationEventService;
use Iwoca\Iwocapay\Model\IwocaClientFactory;
use Iwoca\Iwocapay\Controller\Process\Callback;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Magento\Sales\Model\Order;
use Magento\Framework\Stdlib\DateTime\DateTime;

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
    protected $TERMINAL_STATE_ERROR = CancelOrderOutcomePolicy::TERMINAL_STATE_ERROR;
    protected $ERROR_CANCELLING_TERMINAL_STATE = "Unable to cancel order locally due to unexpected terminal status via iwocaPay.";
    protected $SUCCESSFUL_SYNC_STATE = "Cancelled stranded order via iwocaPay.";
    private $dateTime;

    /**
     * @var IntegrationEventService
     */
    private $eventService;

    public function __construct(
        LoggerInterface         $logger,
        OrderFactory            $orderFactory,
        IwocaClientFactory      $iwocaClientFactory,
        Config                  $config,
        DateTime                $dateTime,
        IntegrationEventService $eventService
    ) {
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->iwocaClientFactory = $iwocaClientFactory;
        $this->config = $config;
        $this->dateTime = $dateTime;
        $this->eventService = $eventService;
    }

    protected function getCancelledIwocaPayOrders(): OrderCollection
    {
        $threeDaysAgo = $this->dateTime->gmtDate(
            'Y-m-d H:i:s',
            $this->dateTime->gmtTimestamp() - (3 * 24 * 60 * 60)
        );

        return $this->orderFactory->create()->getCollection()
            ->join(
                ['payment' => 'sales_order_payment'],
                'main_table.entity_id = payment.parent_id',
                ['method']
            )
            ->addFieldToFilter('payment.method', ['in' => ['iwocapay', 'iwocapay_paylater', 'iwocapay_paynow']])
            // Filter on state, not status. STATE_CANCELED is a *state* value; it only matches the
            // status column because Magento's default status for that state shares the name. A
            // merchant with a custom status assigned to the canceled state would otherwise have
            // their cancelled orders invisible here forever, holding stock against pay links we
            // never release -- the exact outcome this cron exists to prevent.
            ->addFieldToFilter('main_table.state', Order::STATE_CANCELED)
            ->addFieldToFilter('main_table.created_at', ['gteq' => $threeDaysAgo]); // first protection against querying old orders
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

    protected function shouldNotRunOnOrder($order): bool
    {
        foreach ($order->getStatusHistoryCollection() as $comment) {
            $commentText = $comment->getComment();
            if ($commentText === null) {
                continue;
            }

            if (strpos($commentText, $this->ERROR_CANCELLING_TERMINAL_STATE) !== false) {
                return true;
            }

            if (strpos($commentText, $this->SUCCESSFUL_SYNC_STATE) !== false) {
                return true;
            }
        }

        return false;
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

    protected function extractErrorMessageFromException(RequestException $e): string
    {
        if (!$e->hasResponse()) {
            return 'No response received';
        }

        $body = (string) $e->getResponse()->getBody();
        $data = json_decode($body, true);
        if (
            is_array($data) &&
            isset($data['errors'][0]['detail']) &&
            is_string($data['errors'][0]['detail'])
        ) {
            return $data['errors'][0]['detail'];
        }

        return 'Unknown error';
    }

    protected function cancelIwocaPayOrder($order): void
    {
        $extractedOrderID = $this->findUUIDInOrderComments($order);
        if ($extractedOrderID === null) {
            $this->eventService->report(
                IntegrationEventService::EVENT_ORDER_FAILED_TO_RECONCILE,
                [
                    'reason' => 'ORDER_ID_NOT_FOUND_IN_COMMENTS',
                    'source' => 'cancel_cron',
                    'magento_increment_id' => $order->getIncrementId(),
                ]
            );
            throw new LocalizedException(__('Unable to retrieve order ID.'));
        }

        try {
            $this->iwocaClientFactory->create()->delete(
                $this->config->getApiEndpoint(
                    Config::CONFIG_TYPE_CANCEL_ORDER_ENDPOINT,
                    [':' . Callback::IWOCA_ORDER_ID_PARAM => $extractedOrderID]
                )
            );
            $order->addStatusHistoryComment("CancelAbandonedOrders: {$this->SUCCESSFUL_SYNC_STATE}");
            $order->save();
        } catch (ClientException $e) {
            $isTerminalState = false;
            $sentinelPersisted = false;
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 400) {
                try {
                    $exceptionReason = $this->extractErrorMessageFromException($e);
                    if ($exceptionReason===$this->TERMINAL_STATE_ERROR) {
                        $isTerminalState = true;
                        // Sentinel string -- shouldNotRunOnOrder() matches on it, so keep it stable
                        // and put the merchant-readable detail in a separate comment.
                        $order->addStatusHistoryComment("CancelAbandonedOrders: {$this->ERROR_CANCELLING_TERMINAL_STATE}");
                        $order->addStatusHistoryComment(
                            __(
                                'iwocaPay could not cancel this pay link because the buyer appears to have '
                                . 'already paid. Stock may have been released for an order that was paid for. '
                                . 'Please contact iwocaPay support.'
                            )
                        );
                        $order->save();
                        $sentinelPersisted = true;
                    }
                } catch (\Exception $saveException) {
                    $this->logger->error("CancelAbandonedOrder: Error while saving order status of {$order->getId()}. Error: {$saveException->getMessage()}");
                }
            }
            if ($isTerminalState) {
                // Money has most likely moved while the Magento order was cancelled and its stock
                // released -- the worst outcome the flow can produce, so it is always reported.
                // When the sentinel persisted, shouldNotRunOnOrder() bars the order from future
                // runs and this fires once. When the save failed it will fire again every tick
                // until the three-day window closes, so flag which case this is rather than
                // leaving a triager to guess at identical duplicates.
                if (!$sentinelPersisted) {
                    $this->logger->error(
                        "CancelAbandonedOrder: terminal-state sentinel did not persist for order "
                        . "{$order->getIncrementId()}; this escalation will repeat until the order "
                        . "ages out of the three-day window."
                    );
                }
                $this->eventService->report(
                    IntegrationEventService::EVENT_ORDER_FAILED_TO_RECONCILE,
                    [
                        'reason' => 'PAID_AFTER_CANCELLATION',
                        'source' => 'cancel_cron',
                        'iwocapay_order_id' => $extractedOrderID,
                        'magento_increment_id' => $order->getIncrementId(),
                        'sentinel_persisted' => $sentinelPersisted,
                    ]
                );
            } else {
                $this->eventService->report(
                    IntegrationEventService::EVENT_API_CALL_FAILED,
                    array_merge(
                        [
                            'endpoint' => 'ecommerce/order/cancel',
                            'source' => 'cancel_cron',
                            'iwocapay_order_id' => $extractedOrderID,
                        ],
                        $this->eventService->apiErrorContext($e)
                    ),
                    $this->eventService->shouldSendForException($e)
                );
            }
            throw new LocalizedException(__('An error occurred: %1', $e->getMessage()), $e);
        } catch (GuzzleException|LocalizedException $e) {
            $this->eventService->report(
                IntegrationEventService::EVENT_API_CALL_FAILED,
                array_merge(
                    [
                        'endpoint' => 'ecommerce/order/cancel',
                        'source' => 'cancel_cron',
                        'iwocapay_order_id' => $extractedOrderID,
                    ],
                    $this->eventService->apiErrorContext($e)
                ),
                $this->eventService->shouldSendForException($e)
            );
            throw new LocalizedException(__('An error occurred: %1', $e->getMessage()), $e);
        } catch (\Throwable $e) {
            // Nothing above handled this, so it is unreported -- and a raw Error here is the
            // failure class that kept this cron broken and invisible for six months. Report it
            // now, while the true type and message are intact: once rethrown below it is a
            // LocalizedException whose message has been reformatted, and apiErrorContext()
            // would describe the wrapper instead of the cause. Sent unconditionally because
            // shouldSendForException() models API status codes, which an Error does not have.
            $this->eventService->report(
                IntegrationEventService::EVENT_UNHANDLED_EXCEPTION,
                array_merge(
                    [
                        'source' => 'cancel_cron',
                        'iwocapay_order_id' => $extractedOrderID,
                        'magento_increment_id' => $order->getIncrementId(),
                        'exception_class' => get_class($e),
                    ],
                    $this->eventService->apiErrorContext($e)
                )
            );
            throw new LocalizedException(__('An error occurred: %1', $e->getMessage()), $e);
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
                if (!$this->isOrderMarkedForCancellation($order) || $this->shouldNotRunOnOrder($order)) {
                    continue;
                }
                try {
                    $this->cancelIwocaPayOrder($order);
                    $this->markOrderAsCancelled($order);
                } catch (\Throwable $e) {
                    // \Throwable, not \Exception: an Error here (e.g. an undefined method on the
                    // client) would otherwise escape the loop and abandon every remaining order.
                    $this->logger->error("CancelAbandonedOrder: Unable to cancel order with internal id {$order->getId()}. Error: {$e->getMessage()}");

                    // cancelIwocaPayOrder() reports every failure it sees, including raw Errors,
                    // and rethrows them all as LocalizedException -- so reporting a
                    // LocalizedException again here would double-count it.
                    //
                    // This is a type check standing in for an origin check, and the two are not
                    // the same: markOrderAsCancelled()'s save throws CouldNotSaveException, which
                    // extends LocalizedException, so a failure there is suppressed here despite
                    // being unreported. Tolerated because that method is cosmetic -- the retry
                    // gate is SUCCESSFUL_SYNC_STATE, already written inside cancelIwocaPayOrder()
                    // before this point. A marker exception would make the check exact; see
                    // IP-3649.
                    if (!$e instanceof LocalizedException) {
                        $this->eventService->report(
                            IntegrationEventService::EVENT_UNHANDLED_EXCEPTION,
                            array_merge(
                                [
                                    'source' => 'cancel_cron',
                                    'magento_increment_id' => $order->getIncrementId(),
                                ],
                                $this->eventService->apiErrorContext($e)
                            )
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('CancelAbandonedOrder: Error in CancelAbandonedOrder CRON job' . $e->getMessage());
            $this->eventService->report(
                IntegrationEventService::EVENT_UNHANDLED_EXCEPTION,
                array_merge(
                    ['source' => 'cancel_cron_job'],
                    $this->eventService->apiErrorContext($e)
                )
            );
        }
    }
}
