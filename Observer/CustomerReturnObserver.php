<?php

declare(strict_types=1);

namespace Iwoca\Iwocapay\Observer;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Iwoca\Iwocapay\Controller\Process\Callback;
use Iwoca\Iwocapay\Helper\PageIdentifier;
use Iwoca\Iwocapay\Helper\PageType;
use Iwoca\Iwocapay\Model\CancelOrderOutcomePolicy;
use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\IntegrationEventService;
use Iwoca\Iwocapay\Model\IwocaClientFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\OrderFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/*
This observer handles the restoration on iwocaPay shopping carts.
If a customer checks out with iwocaPay and returns via the back button
the order is canceled and the quote is restored.

It checks the following conditions:
- If the request is not for a page, don't do anything
- If the request is for any page other than the checkout or cart don't do anything
- If the request is for any page other than the checkout or cart and the referrer is not the store:
    This is a proxy for a customer reentering the store not using the back button
    Set the allow_cancel_order flag to false
    (This prevents the order from being canceled when the customer returns to the store via URL navigation
- If the request is for the checkout or cart page:
    - check if the allow_cancel_order flag is set to true.
       (this only happens if the customer is returning to the store via the back button)
    - check if the current quote is empty (this avoids overriding the quote with the previous one)
    - check if the last order was paid with iwocaPay
    - cancel the pay link in iwocaPay first, so the buyer can no longer pay for an order we are about to cancel
       - if iwocaPay reports it already paid, leave the order and the quote alone
       - if iwocaPay can't be reached, carry on and leave the order marked for the cancel cron to retry
    - cancel the previous order
    - restore the quote
- If the request is a redirect to iwocaPay:
    Set the allow_cancel_order flag to true
    (this allows the order to be canceled when the customer returns to the store via the back button)

                Redirect
              - Referrer === Checkout location
              - Redirect to outside store
+-----------+ => set allow_cancel_order flag to true +----------+
| Checkout  +--------------------------------------> | iwocaPay |
+-----------+                                        +----+-+---+
     ^ ^                                                  | |
     | |                                                  | |
     | +--------------------------------------------------+ |
     |            Back Button                               |
     |            - Location === Checkout || Cart           |
     |            - allow_cancel_order === true             |
     |            => Cancel latest iwocaPay order           |
     |            => Recreate latest iwocaPay quote         |
     |                                                      |
  +--+----+                                                 |
  | Store | <-----------------------------------------------+
  +-------+    Navigation
               - No referrer
               - Location !== Checkout || Cart
               => set allow_cancel_order_flag to false
 */

class CustomerReturnObserver implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var PageIdentifier
     */
    protected $pageIdentifier;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var IwocaClientFactory
     */
    protected $iwocaClientFactory;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var IntegrationEventService
     */
    protected $eventService;

    /**
     * @var CancelOrderOutcomePolicy
     */
    protected $cancelOrderOutcomePolicy;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param QuoteRepository $quoteRepository
     * @param CheckoutSession $checkoutSession
     * @param OrderFactory $orderFactory
     * @param PageIdentifier $pageIdentifier
     * @param CartRepositoryInterface $cartRepository
     * @param RequestInterface $request
     * @param ScopeConfigInterface $scopeConfig
     * @param IwocaClientFactory $iwocaClientFactory
     * @param Config $config
     * @param IntegrationEventService $eventService
     * @param CancelOrderOutcomePolicy $cancelOrderOutcomePolicy
     */
    public function __construct(
        LoggerInterface          $logger,
        QuoteRepository          $quoteRepository,
        CheckoutSession          $checkoutSession,
        OrderFactory             $orderFactory,
        PageIdentifier           $pageIdentifier,
        CartRepositoryInterface  $cartRepository,
        RequestInterface         $request,
        ScopeConfigInterface     $scopeConfig,
        IwocaClientFactory       $iwocaClientFactory,
        Config                   $config,
        IntegrationEventService  $eventService,
        CancelOrderOutcomePolicy $cancelOrderOutcomePolicy,
    ) {
        $this->logger = $logger;
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->pageIdentifier = $pageIdentifier;
        $this->cartRepository = $cartRepository;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->iwocaClientFactory = $iwocaClientFactory;
        $this->config = $config;
        $this->eventService = $eventService;
        $this->cancelOrderOutcomePolicy = $cancelOrderOutcomePolicy;
    }


    /**
     * Cancel previous order and reactivate the quote when returning to the checkout cart
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $pageType = $this->pageIdentifier->getType();
        $isLandingPage = $this->pageIdentifier->isLandingPage();

        if ($pageType === PageType::NOT_A_PAGE) {
            return;
        }

        if ($pageType === PageType::UNKNOWN && $isLandingPage) {
            $this->checkoutSession->setData('allow_cancel_order', false);
        }

        if ($pageType === PageType::EXTERNAL_REDIRECT) {
            $this->checkoutSession->setData('allow_cancel_order', true);
            return;
        }

        if (!$this->checkoutSession->getData('allow_cancel_order')) {
            return;
        }

        $currentQuote = $this->checkoutSession->getQuote();

        if ($currentQuote->getItemsCount() > 0) {
            return;
        }

        $orderId = $this->checkoutSession->getLastOrderId();

        if (!$orderId) {
            return;
        }

        $order = $this->orderFactory->create()->load($orderId);
        $payment = $order->getPayment();

        if (
            !$payment ||
            !in_array($payment->getMethod(), ['iwocapay', 'iwocapay_paylater', 'iwocapay_paynow'], true)
        ) {
            return;
        }

        if ($order->getStatus() !== \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {
            return;
        }

        if (!$order->canCancel()) {
            return;
        }

        $order = $this->handleIwocaPayCancellation($order, $orderId);
        if ($order === null) {
            return;
        }

        $this->restoreQuote($order);
    }

    private function handleIwocaPayCancellation(\Magento\Sales\Model\Order $order, $orderId): ?\Magento\Sales\Model\Order
    {
        $isAbandonedCartCancellationDisabled = $this->scopeConfig->getValue(
            'payment/iwocapay/advanced/disable_abandoned_cart_cancellations',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($isAbandonedCartCancellationDisabled) {
            $order->addStatusHistoryComment(__('User returned to checkout cart without completing iwocaPay. iwocaPay will not automatically cancel the order as this option is disabled in settings.'));
            $order->save();
            return $order;
        }

        $outcome = $this->cancelInIwocaPay($order);

        // Reload: the callback may have invoiced it while we waited on iwocaPay.
        $order = $this->orderFactory->create()->load($orderId);
        if ($order->getStatus() !== \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {
            return null;
        }

        if (!$this->cancelOrderOutcomePolicy->shouldCancelLocally($outcome)) {
            // Restoring the quote would hand a live basket to a buyer who has already paid.
            $this->checkoutSession->setData('allow_cancel_order', false);
            try {
                $order->addStatusHistoryComment(__('User returned to checkout cart, but iwocaPay reports this order as already paid, so it has not been cancelled.'));
                $order->save();
            } catch (\Throwable $e) {
                $this->logger->error('Error saving order: ' . $e->getMessage());
            }
            return null;
        }

        try {
            $order->cancel();
            $order->addStatusHistoryComment(__('Order canceled as user returned to checkout cart without completing iwocaPay.'));
            if ($outcome === CancelOrderOutcomePolicy::OUTCOME_CANCELLED) {
                $order->addStatusHistoryComment(__('Cancelled in iwocaPay.'));
            } else {
                // The cancel cron picks orders up by this comment and retries the cancel.
                $order->addStatusHistoryComment(__('Marked for cancellation in iwocaPay.'));
            }
            $order->save();
        } catch (\Throwable $e) {
            $this->logger->error('Error canceling order: ' . $e->getMessage());
        }

        return $order;
    }

    private function restoreQuote(\Magento\Sales\Model\Order $order): void
    {
        $quoteId = $order->getQuoteId();

        if (!$quoteId) {
            return;
        }

        try {
            $quote = $this->quoteRepository->get($quoteId);
        } catch (\Throwable $e) {
            $this->logger->error('Error loading quote: ' . $e->getMessage());
            return;
        }

        if (!$quote) {
            return;
        }

        foreach ($quote->getAllItems() as $item) {
            $item->setIsActive(true);
            $item->setQty($item->getQty());
        }

        $quote->setIsActive(true)->save();

        try {
            $this->quoteRepository->save($quote); // Save the quote to ensure changes are persisted
        } catch (\Throwable $e) {
            $this->logger->error('Error saving quote: ' . $e->getMessage());
            return;
        }

        $this->checkoutSession->replaceQuote($quote);
    }

    // Must not throw: runs before every storefront response.
    private function cancelInIwocaPay(\Magento\Sales\Model\Order $order): string
    {
        $iwocaOrderId = $order->getPayment()->getAdditionalInformation('iwocapay_order_id');
        if (!$iwocaOrderId) {
            return CancelOrderOutcomePolicy::OUTCOME_DEFER_TO_CRON;
        }

        try {
            $response = $this->iwocaClientFactory->create()->delete(
                $this->config->getApiEndpoint(
                    Config::CONFIG_TYPE_CANCEL_ORDER_ENDPOINT,
                    [':' . Callback::IWOCA_ORDER_ID_PARAM => $iwocaOrderId]
                ),
                [RequestOptions::TIMEOUT => 2, RequestOptions::CONNECT_TIMEOUT => 1]
            );

            return $this->cancelOrderOutcomePolicy->decide($response->getStatusCode(), null);
        } catch (\Throwable $e) {
            try {
                $response = $e instanceof RequestException ? $e->getResponse() : null;
                $outcome = $this->cancelOrderOutcomePolicy->decide(
                    $response ? $response->getStatusCode() : null,
                    $response ? $this->eventService->extractResponseErrorDetail($response) : null
                );
            } catch (\Throwable $classificationException) {
                $outcome = CancelOrderOutcomePolicy::OUTCOME_DEFER_TO_CRON;
            }

            if ($outcome === CancelOrderOutcomePolicy::OUTCOME_DEFER_TO_CRON) {
                $this->reportCancelFailure($e, $iwocaOrderId);
            }

            return $outcome;
        }
    }

    private function reportCancelFailure(\Throwable $e, string $iwocaOrderId): void
    {
        try {
            if ($e instanceof GuzzleException) {
                $this->eventService->report(
                    IntegrationEventService::EVENT_API_CALL_FAILED,
                    array_merge(
                        [
                            'endpoint' => 'ecommerce/order/cancel',
                            'source' => 'customer_return',
                            'iwocapay_order_id' => $iwocaOrderId,
                        ],
                        $this->eventService->apiErrorContext($e)
                    ),
                    $this->eventService->shouldSendForException($e)
                );
                return;
            }

            $this->eventService->report(
                IntegrationEventService::EVENT_UNHANDLED_EXCEPTION,
                array_merge(
                    [
                        'source' => 'customer_return',
                        'iwocapay_order_id' => $iwocaOrderId,
                        'exception_class' => get_class($e),
                    ],
                    $this->eventService->apiErrorContext($e)
                )
            );
        } catch (\Throwable $reportException) {
            $this->logger->error('Error reporting failed iwocaPay cancellation: ' . $reportException->getMessage());
        }
    }
}
