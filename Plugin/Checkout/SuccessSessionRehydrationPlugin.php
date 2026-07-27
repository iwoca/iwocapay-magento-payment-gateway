<?php

declare(strict_types=1);

namespace Iwoca\Iwocapay\Plugin\Checkout;

use Iwoca\Iwocapay\Model\IwocaOrderLocator;
use Iwoca\Iwocapay\Model\SuccessRehydrationPolicy;
use Magento\Checkout\Controller\Onepage\Success;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Restores the checkout "last order" session markers on the native success page
 * when a buyer returns to complete iwocaPay checkout in a dropped or cross-device
 * session (see IP-3483). The order is always fulfilled by the callback; this only
 * repairs the buyer's landing so the confirmation page renders instead of core
 * silently bouncing an empty session to the cart.
 *
 * Display-only: resolves an already-paid order and writes session markers. It
 * never invoices, captures, transitions or saves.
 */
class SuccessSessionRehydrationPlugin
{
    public const IWOCA_ORDER_ID_PARAM = 'iwocapay_order_id';

    private RequestInterface $request;
    private CheckoutSession $checkoutSession;
    private IwocaOrderLocator $iwocaOrderLocator;
    private SuccessRehydrationPolicy $rehydrationPolicy;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        CheckoutSession $checkoutSession,
        IwocaOrderLocator $iwocaOrderLocator,
        SuccessRehydrationPolicy $rehydrationPolicy,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->iwocaOrderLocator = $iwocaOrderLocator;
        $this->rehydrationPolicy = $rehydrationPolicy;
        $this->logger = $logger;
    }

    /**
     * @param Success $subject
     * @return void
     */
    public function beforeExecute(Success $subject): void
    {
        $iwocaOrderId = (string) $this->request->getParam(self::IWOCA_ORDER_ID_PARAM);
        if ($iwocaOrderId === '') {
            return;
        }

        // Cheap short-circuit before any DB work: never override a valid session.
        if ($this->sessionAlreadyPopulated()) {
            return;
        }

        // Fail open: this only runs for a cold-session buyer (warm sessions
        // short-circuit above). If anything throws, log and let core fall back to
        // its normal empty-session behaviour rather than 500-ing the success page.
        try {
            $order = $this->iwocaOrderLocator->findByIwocaOrderId($iwocaOrderId);
            if ($order === null || !$order->getId()) {
                return;
            }

            $paymentMethod = $order->getPayment() ? $order->getPayment()->getMethod() : null;

            if (!$this->rehydrationPolicy->shouldRehydrate(
                false,
                $paymentMethod,
                (string) $order->getState()
            )) {
                return;
            }

            $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastOrderId($order->getId());
            $this->checkoutSession->setLastRealOrderId($order->getRealOrderId());
        } catch (\Throwable $e) {
            $this->logger->error(
                'iwocaPay success rehydration failed for order ' . $iwocaOrderId . ': ' . $e->getMessage()
            );
        }
    }

    private function sessionAlreadyPopulated(): bool
    {
        return (bool) $this->checkoutSession->getLastSuccessQuoteId()
            && (bool) $this->checkoutSession->getLastQuoteId()
            && (bool) $this->checkoutSession->getLastOrderId();
    }
}
