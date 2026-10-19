<?php

declare(strict_types=1);

namespace Iwoca\Iwocapay\Controller\Process;

use GuzzleHttp\Exception\GuzzleException;
use Iwoca\Iwocapay\Api\Response\GetOrderInterface;
use Iwoca\Iwocapay\Model\CallbackOutcomePolicy;
use Iwoca\Iwocapay\Model\IntegrationEventService;
use Iwoca\Iwocapay\Model\IwocaApiClient;
use Iwoca\Iwocapay\Model\IwocaOrderLocator;
use Iwoca\Iwocapay\Model\LegacyOrderLookup;
use Iwoca\Iwocapay\Plugin\Checkout\SuccessSessionRehydrationPlugin;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Callback implements HttpGetActionInterface
{
    public const IWOCA_ORDER_ID_PARAM = 'orderId';

    private Context $context;
    private ResultFactory $resultFactory;
    private IwocaApiClient $iwocaApiClient;
    private Session $checkoutSession;
    private OrderFactory $orderFactory;
    private OrderRepositoryInterface $orderRepository;
    private ManagerInterface $messageManager;
    private CartRepositoryInterface $quoteRepository;
    private InvoiceService $invoiceService;
    private InvoiceRepositoryInterface $invoiceRepository;
    private InvoiceSender $invoiceSender;
    private OrderSender $orderSender;
    private LoggerInterface $logger;
    private ScopeConfigInterface $scopeConfig;
    private IwocaOrderLocator $iwocaOrderLocator;
    private LegacyOrderLookup $legacyOrderLookup;
    private IntegrationEventService $eventService;
    private CallbackOutcomePolicy $outcomePolicy;

    /**
     * @param Context $context
     * @param ResultFactory $resultFactory
     * @param IwocaApiClient $iwocaApiClient
     * @param Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param ManagerInterface $messageManager
     * @param CartRepositoryInterface $quoteRepository
     * @param InvoiceService $invoiceService
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param InvoiceSender $invoiceSender
     * @param OrderSender $orderSender
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param IwocaOrderLocator $iwocaOrderLocator
     * @param LegacyOrderLookup $legacyOrderLookup
     * @param IntegrationEventService $eventService
     * @param CallbackOutcomePolicy $outcomePolicy
     */
    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        IwocaApiClient $iwocaApiClient,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        OrderRepositoryInterface $orderRepository,
        ManagerInterface $messageManager,
        CartRepositoryInterface $quoteRepository,
        InvoiceService $invoiceService,
        InvoiceRepositoryInterface $invoiceRepository,
        InvoiceSender $invoiceSender,
        OrderSender $orderSender,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        IwocaOrderLocator $iwocaOrderLocator,
        LegacyOrderLookup $legacyOrderLookup,
        IntegrationEventService $eventService,
        CallbackOutcomePolicy $outcomePolicy
    ) {
        $this->context = $context;
        $this->resultFactory = $resultFactory;
        $this->iwocaApiClient = $iwocaApiClient;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->orderRepository = $orderRepository;
        $this->messageManager = $messageManager;
        $this->quoteRepository = $quoteRepository;
        $this->invoiceService = $invoiceService;
        $this->invoiceRepository = $invoiceRepository;
        $this->invoiceSender = $invoiceSender;
        $this->orderSender = $orderSender;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->iwocaOrderLocator = $iwocaOrderLocator;
        $this->legacyOrderLookup = $legacyOrderLookup;
        $this->eventService = $eventService;
        $this->outcomePolicy = $outcomePolicy;
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function execute()
    {
        $iwocaOrderId = $this->context->getRequest()->getParam(self::IWOCA_ORDER_ID_PARAM);

        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if (!$iwocaOrderId) {
            $this->logger->error('iwocaPay callback: Missing orderId parameter');
            return $this->handleFailure($redirect);
        }

        try {
            $orderResponse = $this->iwocaApiClient->getOrder($iwocaOrderId);
        } catch (GuzzleException | LocalizedException $e) {
            $this->logger->error('iwocaPay callback: Failed to get order response - ' . $e->getMessage());
            $this->eventService->report(
                IntegrationEventService::EVENT_API_CALL_FAILED,
                array_merge(
                    [
                        'endpoint' => 'ecommerce/order/fetch',
                        'iwocapay_order_id' => $iwocaOrderId,
                    ],
                    $this->eventService->apiErrorContext($e)
                ),
                $this->eventService->shouldSendForException($e)
            );
            return $this->handleFailure($redirect);
        }

        // Look up order by iwoca order ID stored in payment additional information
        $magentoOrder = $this->findOrderByIwocaOrderId($iwocaOrderId);

        // Fallback for legacy orders without stored iwocapay_order_id
        $doesOrderContainIwocaOrderReference = !$magentoOrder || !$magentoOrder->getId();
        if ($doesOrderContainIwocaOrderReference) {
            $magentoOrder = $this->findOrderWithReference($iwocaOrderId, $orderResponse);
            if (!$magentoOrder) {
                return $redirect->setUrl('/checkout/cart');
            }
        }

        $magentoOrder->addCommentToStatusHistory(__('Iwoca order callback initiated.'));

        if (!$this->validateOrderCanBeProcessed($magentoOrder, $iwocaOrderId)) {
            return $this->handleFailure($redirect, $magentoOrder, 'Callback validation failed');
        }

        $statusCode = $orderResponse->getStatus();
        if ($statusCode !== GetOrderInterface::STATUS_CODE_SUCCESSFUL) {
            $this->addDebugLog(
                sprintf(
                    'Received status %s for order with increment ID %s',
                    $statusCode,
                    $magentoOrder->getIncrementId()
                )
            );
            return $this->handleFailure($redirect, $magentoOrder, $statusCode);
        }

        // The buyer has paid by this point, so nothing below may surface as a 500.
        // \Throwable rather than LocalizedException: a deadlock or PDOException out of
        // orderRepository->save() is neither, and would still 500 someone who has paid.
        try {
            $outcome = $this->handleSuccess($magentoOrder, $orderResponse);
        } catch (\Throwable $e) {
            return $this->handleFinalisationFailure($redirect, $magentoOrder, $iwocaOrderId, $e);
        }

        if ($this->outcomePolicy->isUnfulfillable($outcome)) {
            return $this->handlePaidWithoutInvoice($redirect, $magentoOrder, $iwocaOrderId, $outcome);
        }

        // Carry the iwoca order id so the success page can rehydrate its session
        // markers if the buyer returns in a dropped/cross-device session (IP-3483).
        return $redirect->setUrl(
            '/checkout/onepage/success?'
            . SuccessSessionRehydrationPlugin::IWOCA_ORDER_ID_PARAM
            . '=' . rawurlencode($iwocaOrderId)
        );
    }

    /**
     * @param Redirect $redirect
     * @param OrderInterface|null $order
     * @param string|null $statusCode
     * @return Redirect
     * @throws NoSuchEntityException
     */
    private function handleFailure(Redirect $redirect, ?OrderInterface $order = null, ?string $statusCode = null): Redirect
    {
        if ($order) {
            $order->addCommentToStatusHistory(
                __(
                    'Received status code %1. Canceling order, recreating quote and redirecting user to cart to retry',
                    $statusCode
                )
            );

            $order->setState(Order::STATE_CANCELED);
            $order->setStatus(Order::STATE_CANCELED);

            $this->orderRepository->save($order);

            $this->activateQuoteFromOrder($order);
        }

        $this->messageManager->addErrorMessage(__('Something went wrong while placing your order.'));
        $redirect->setUrl('/checkout/cart');

        return $redirect;
    }

    /**
     * Best-effort basket restore. Magento's quote-cleanup cron can purge the quote, which
     * makes quoteRepository->get() throw -- and this runs on every failed callback, so an
     * unguarded throw here is a 500 on the most trafficked error path in the controller.
     * An empty cart is a far better outcome than that.
     *
     * @param OrderInterface $order
     * @return void
     */
    private function activateQuoteFromOrder(OrderInterface $order): void
    {
        $quoteId = $order->getQuoteId();

        try {
            $quote = $this->quoteRepository->get($quoteId);
            $quote->setIsActive(1);
            $this->quoteRepository->save($quote);
            $this->checkoutSession->setQuoteId($quoteId);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'iwocaPay callback: could not reactivate quote %s for order %s - %s',
                (string)$quoteId,
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }

    private function setCheckoutSessionOrderData(Order $order): void
    {
        $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastOrderId($order->getId());
        $this->checkoutSession->setLastRealOrderId($order->getRealOrderId());
    }

    /**
     * @param Order $order
     * @param GetOrderInterface $orderResponse
     * @return string One of the CallbackOutcomePolicy::OUTCOME_* constants
     * @throws LocalizedException
     */
    private function handleSuccess(Order $order, GetOrderInterface $orderResponse): string
    {
        $outcome = $this->outcomePolicy->decide(
            $order->getState(),
            $order->hasInvoices(),
            $order->canInvoice()
        );

        if ($outcome === CallbackOutcomePolicy::OUTCOME_ALREADY_PROCESSED) {
            $this->logger->info("iwocaPay handleSuccess: Order {$order->getId()} already processed.");
            $this->setCheckoutSessionOrderData($order);
            return $outcome;
        }

        // Cancellation is terminal on both sides: never reopen, never invoice.
        if ($outcome !== CallbackOutcomePolicy::OUTCOME_INVOICE) {
            return $outcome;
        }

        $this->createOrderInvoice($order, $orderResponse);
        $this->setCheckoutSessionOrderData($order);

        $order->addCommentToStatusHistory(__(
            'Iwoca payment with amount "%1" was successful. Redirecting customer to success page.',
            $orderResponse->getAmount()
        ));

        $order->setState(Order::STATE_PROCESSING);
        $order->setStatus(Order::STATE_PROCESSING);
        $order->setCanSendNewEmailFlag(true);
        $this->orderSender->send($order);
        $this->orderRepository->save($order);

        return $outcome;
    }

    /**
     * Payment landed but the order cannot be invoiced -- almost always because Magento
     * had already cancelled it while the buyer was away.
     *
     * We deliberately do not reuse handleFailure(): that reactivates the quote, and
     * handing a live basket to someone who has just paid invites a second payment.
     *
     * @param Redirect $redirect
     * @param Order $order
     * @param string $iwocaOrderId
     * @param string $outcome
     * @return Redirect
     */
    private function handlePaidWithoutInvoice(
        Redirect $redirect,
        Order $order,
        string $iwocaOrderId,
        string $outcome
    ): Redirect {
        $isCancelled = $outcome === CallbackOutcomePolicy::OUTCOME_PAID_AFTER_CANCELLATION;
        $state = (string)$order->getState();

        if ($isCancelled) {
            $comment = __(
                'iwocaPay payment received after this order was cancelled. No invoice has been raised '
                . 'and this order needs resolving. Please contact iwocaPay support.'
            );
        } else {
            $comment = __(
                'iwocaPay payment received but this order cannot be invoiced (state "%1"). No invoice has '
                . 'been raised and this order needs resolving. Please contact iwocaPay support.',
                $state
            );
        }

        return $this->recordUnfulfillableOrder(
            $redirect,
            $order,
            $comment,
            sprintf(
                'iwocaPay callback: payment received for order %s which cannot be invoiced '
                . '(state %s, outcome %s) - needs manual reconciliation',
                $order->getIncrementId(),
                $state,
                $outcome
            ),
            $isCancelled ? 'PAID_AFTER_CANCELLATION' : 'PAID_BUT_NOT_INVOICEABLE',
            ['iwocapay_order_id' => $iwocaOrderId]
        );
    }

    /**
     * Last-resort net so a paid buyer never sees a 500 from this controller.
     *
     * @param Redirect $redirect
     * @param Order $order
     * @param string $iwocaOrderId
     * @param \Throwable $exception
     * @return Redirect
     */
    private function handleFinalisationFailure(
        Redirect $redirect,
        Order $order,
        string $iwocaOrderId,
        \Throwable $exception
    ): Redirect {
        // Deliberately not hasInvoices(): InvoiceService::prepareInvoice() pushes the invoice
        // onto the order's in-memory collection before register()/save() run, so hasInvoices()
        // is already true when either of those throws -- and we would tell the merchant the
        // money was invoiced when nothing persisted and nothing was captured. Only a saved
        // invoice carries an id.
        // The probe itself can throw: getInvoiceCollection() lazily loads, so on the DB-failure
        // path this handler exists for it may issue a query that fails. Default to the
        // conservative wording -- which is also the honest answer when we cannot confirm.
        $invoiced = false;
        try {
            $invoiced = $this->hasPersistedInvoice($order);
        } catch (\Throwable $probeException) {
            $this->quietly('logging the invoice-probe failure', function () use ($order, $probeException) {
                $this->logger->error(sprintf(
                    'iwocaPay callback: could not determine invoice state for order %s - %s',
                    $order->getIncrementId(),
                    $probeException->getMessage()
                ));
            });
        }

        if ($invoiced) {
            $comment = __(
                'iwocaPay payment received and invoiced, but this order could not be fully updated. '
                . 'Please contact iwocaPay support.'
            );
        } else {
            $comment = __(
                'iwocaPay payment received but this order could not be updated. No invoice has been raised '
                . 'and this order needs resolving. Please contact iwocaPay support.'
            );
        }

        return $this->recordUnfulfillableOrder(
            $redirect,
            $order,
            $comment,
            sprintf(
                'iwocaPay callback: failed to finalise paid order %s - %s - needs manual reconciliation',
                $order->getIncrementId(),
                $exception->getMessage()
            ),
            'FINALISATION_FAILED',
            array_merge(
                ['iwocapay_order_id' => $iwocaOrderId],
                $this->eventService->apiErrorContext($exception)
            )
        );
    }

    /**
     * Shared tail for every "money moved but the order cannot be completed" outcome.
     *
     * Ordering is deliberate: report to iwoca first, because the event is the only signal
     * that reliably reaches us, and the order save is the step most likely to fail (a
     * merchant sales_order_save_after observer rejecting a cancelled order, for instance).
     * The save is then guarded so it can never cost the buyer a redirect.
     *
     * @param Redirect $redirect
     * @param Order $order
     * @param Phrase $comment
     * @param string $logMessage
     * @param string $reason
     * @param array $extraMetaData
     * @return Redirect
     */
    private function recordUnfulfillableOrder(
        Redirect $redirect,
        Order $order,
        Phrase $comment,
        string $logMessage,
        string $reason,
        array $extraMetaData = []
    ): Redirect {
        // Everything below is best-effort. This method exists so a buyer who has paid never
        // sees a 500, so nothing in it may throw -- including the logging and reporting
        // themselves (an unwritable var/log throws out of the stream handler; non-UTF-8
        // metadata throws out of Json::serialize).
        $this->quietly('error logging', function () use ($logMessage) {
            $this->logger->error($logMessage);
        });

        $this->quietly('reporting to iwoca', function () use ($order, $reason, $extraMetaData) {
            $this->eventService->report(
                IntegrationEventService::EVENT_ORDER_FAILED_TO_RECONCILE,
                array_merge(
                    [
                        'reason' => $reason,
                        'source' => 'callback',
                        'magento_increment_id' => $order->getIncrementId(),
                        'magento_order_state' => (string)$order->getState(),
                    ],
                    $extraMetaData
                )
            );
        });

        $this->quietly('annotating the order', function () use ($order, $comment) {
            $order->addCommentToStatusHistory($comment);
            $this->orderRepository->save($order);
        });

        $this->quietly('showing the buyer a message', function () use ($order) {
            $this->messageManager->addErrorMessage(
                __(
                    'We have received your payment, but there was a problem completing your order (%1). '
                    . 'Please contact iwocaPay support.',
                    $order->getIncrementId()
                )
            );
        });

        return $redirect->setPath('/');
    }

    /**
     * Run a best-effort step, swallowing anything it throws but leaving a breadcrumb.
     *
     * Only for use on the paid-buyer failure paths, where surfacing a secondary failure
     * would replace a usable message with the 500 this controller exists to avoid.
     *
     * Swallowing silently is not good enough: the order save here is the step most likely
     * to fail, so losing its reason would make the commonest failure the one with no trace.
     * The log is itself guarded, since an unwritable var/log is one of the ways a step fails.
     *
     * @param string $context
     * @param callable $step
     * @return void
     */
    private function quietly(string $context, callable $step): void
    {
        try {
            $step();
        } catch (\Throwable $suppressed) {
            try {
                $this->logger->error(sprintf(
                    'iwocaPay callback: %s failed and was suppressed - %s: %s',
                    $context,
                    get_class($suppressed),
                    $suppressed->getMessage()
                ));
            } catch (\Throwable $unloggable) {
                // Nowhere left to log to.
            }
        }
    }

    /**
     * Whether a *saved* invoice exists for this order.
     *
     * hasInvoices() cannot answer this: InvoiceService::prepareInvoice() adds the invoice to
     * the order's in-memory collection before register()/save(), so it reports true even when
     * persistence failed. Only a saved invoice has an id.
     *
     * @param Order $order
     * @return bool
     */
    private function hasPersistedInvoice(Order $order): bool
    {
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->getId()) {
                return true;
            }
        }

        return false;
    }


    /**
     * @param Order $order
     * @param GetOrderInterface $orderResponse
     * @return void
     * @throws LocalizedException
     */
    private function createOrderInvoice(Order $order, GetOrderInterface $orderResponse): void
    {
        if (!$order->canInvoice()) {
            $order->addCommentToStatusHistory(__('Order cannot be invoiced'));
            throw new LocalizedException(__('Order cannot be invoiced'));
        }

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
        $invoice->setTransactionId($orderResponse->getPayLinkId());
        $invoice->register();
        $this->invoiceRepository->save($invoice);

        $sendInvoiceEmails = $this->scopeConfig->isSetFlag(
            'payment/iwocapay/send_invoice_emails',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );

        if ($sendInvoiceEmails) {
            $invoice->setEmailSent(true);
            $this->invoiceSender->send($invoice);
        }
    }

    private function isPaymentMethodIwocapay(Order $order): bool
    {
        $orderId = $order->getIncrementId();

        // Validate payment method is iwocapay
        $paymentMethod = $order->getPayment()->getMethod();
        if (!in_array($paymentMethod, ['iwocapay_paylater', 'iwocapay_paynow'], true)) {
            $this->logger->error(
                sprintf(
                    'iwocaPay callback: Invalid payment method %s for order %s',
                    $paymentMethod,
                    $orderId,
                )
            );
            return false;
        }

        return true;
    }

    private function isOrderIwocaIdMatchingCallbackParamId(Order $order, string $callbackIwocaOrderId): bool
    {
        // Validate stored iwoca order ID matches callback parameter (if stored - new orders only)
        $storedIwocaOrderId = $order->getPayment()->getAdditionalInformation('iwocapay_order_id');
        if ($storedIwocaOrderId && $storedIwocaOrderId !== $callbackIwocaOrderId) {
            $this->logger->error(
                sprintf(
                    'iwocaPay callback: Stored iwoca order ID %s does not match callback parameter %s for order %s',
                    $storedIwocaOrderId,
                    $callbackIwocaOrderId,
                    $order->getIncrementId()
                )
            );
            return false;
        }

        return true;
    }

    /**
     * Validate that the order can be updated with callback data
     *
     * @param Order $order
     * @param string $iwocaOrderId
     * @return bool Returns true if valid, false if validation fails
     */
    private function validateOrderCanBeProcessed(Order $order, string $iwocaOrderId): bool
    {
        if ($this->isPaymentMethodIwocapay($order) && $this->isOrderIwocaIdMatchingCallbackParamId($order, $iwocaOrderId)) {
            return true;
        }

        return false;
    }

    /**
     * Find order by iwoca order ID stored in payment additional information
     *
     * @param string $iwocaOrderId
     * @return Order|null
     */
    private function findOrderByIwocaOrderId(string $iwocaOrderId): ?Order
    {
        return $this->iwocaOrderLocator->findByIwocaOrderId($iwocaOrderId);
    }

    /**
     * Handle legacy order lookup for orders created before iwoca_order_id was stored
     *
     * @param string $iwocaOrderId
     * @param GetOrderInterface $orderResponse
     * @return Order|null Returns null if fallback is not active or order not found
     */
    private function findOrderWithReference(
        string $iwocaOrderId,
        GetOrderInterface $orderResponse,
    ): ?Order {
        // Check if fallback is still active (before 2nd June 2026)
        if (!$this->legacyOrderLookup->isActive()) {
            $this->messageManager->addErrorMessage(__('Unable to process your payment. Please contact support.'));
            return null;
        }

        // Attempt legacy lookup by increment_id
        $magentoOrder = $this->orderFactory->create();
        $magentoOrder->loadByIncrementId($orderResponse->getReference());

        if (!$magentoOrder->getId()) {
            $this->logger->error(
                sprintf('iwocaPay callback: Order not found by either method for iwoca order ID %s', $iwocaOrderId)
            );
            $this->eventService->report(
                IntegrationEventService::EVENT_ORDER_FAILED_TO_RECONCILE,
                [
                    'reason' => 'MAGENTO_ORDER_NOT_FOUND',
                    'source' => 'callback',
                    'iwocapay_order_id' => $iwocaOrderId,
                    'attempted_reference' => $orderResponse->getReference(),
                ]
            );
            $this->messageManager->addErrorMessage(__('Unable to find your order. Please contact support.'));
            return null;
        }

        return $magentoOrder;
    }

    /**
     * @param string $logMessage
     * @return void
     */
    private function addDebugLog(string $logMessage): void
    {
        $this->logger->debug($logMessage);
    }
}
