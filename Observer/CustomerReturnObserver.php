<?php

declare(strict_types=1);

namespace Iwoca\Iwocapay\Observer;

use Iwoca\Iwocapay\Helper\PageIdentifier;
use Iwoca\Iwocapay\Helper\PageType;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\OrderFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\App\RequestInterface;

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
    - cancel the previous order
    - make sure enough stoke is available
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
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param QuoteRepository $quoteRepository
     * @param CheckoutSession $checkoutSession
     * @param OrderFactory $orderFactory
     * @param PageIdentifier $pageIdentifier
     * @param CartRepositoryInterface $cartRepository
     * @param RequestInterface $request
     */
    public function __construct(
        LoggerInterface $logger,
        QuoteRepository $quoteRepository,
        CheckoutSession $checkoutSession,
        OrderFactory $orderFactory,
        PageIdentifier $pageIdentifier,
        CartRepositoryInterface $cartRepository,
        RequestInterface $request,
    ) {
        $this->logger = $logger;
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->pageIdentifier = $pageIdentifier;
        $this->cartRepository = $cartRepository;
        $this->request = $request;
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

        if ($currentQuote->getItemsCount() > 0) return;

        $orderId = $this->checkoutSession->getLastOrderId();

        if (!$orderId) return;

        $order = $this->orderFactory->create()->load($orderId);
        $payment = $order->getPayment();

        if (!$payment || $payment->getMethod() !== 'iwocapay') return;

        if ($order->getStatus() !== \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) return;

        if (!$order->canCancel()) return;

        try {
            $order->cancel();
            $order->addStatusHistoryComment(__('Order canceled as user returned to checkout cart without completing iwocaPay.'));
            $order->addStatusHistoryComment(__('Marked for cancellation in iwocaPay.'));
            $order->save();
        } catch (\Exception $e) {
            $this->logger->error('Error canceling order: ' . $e->getMessage());
        }

        $quoteId = $order->getQuoteId();

        if (!$quoteId) return;

        try {
            $quote = $this->quoteRepository->get($quoteId);
        } catch (\Exception $e) {
            $this->logger->error('Error loading quote: ' . $e->getMessage());
            return;
        }

        if (!$quote) return;

        foreach ($quote->getAllItems() as $item) {
            $item->setIsActive(true);
            $item->setQty($item->getQty());
        }

        $quote->setIsActive(true)->save();

        try {
            $this->quoteRepository->save($quote); // Save the quote to ensure changes are persisted
        } catch (\Exception $e) {
            $this->logger->error('Error saving quote: ' . $e->getMessage());
            return;
        }

        $this->checkoutSession->replaceQuote($quote);
    }
}
