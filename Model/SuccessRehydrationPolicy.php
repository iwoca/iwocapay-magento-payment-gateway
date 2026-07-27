<?php

declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

/**
 * Pure decision for whether the checkout success page may rehydrate its
 * last-order session markers from a resolved iwocaPay order.
 *
 * Deliberately framework-free so it can be exercised by the standalone unit
 * suite. The string constants below intentionally duplicate
 * Magento\Sales\Model\Order::STATE_PROCESSING and the method codes in
 * ConfigProvider::CODE_PAY_LATER / CODE_PAY_NOW (see Callback::isPaymentMethodIwocapay);
 * keep them in sync if those ever change.
 */
class SuccessRehydrationPolicy
{
    private const IWOCAPAY_METHODS = ['iwocapay_paylater', 'iwocapay_paynow'];
    private const STATE_PROCESSING = 'processing';

    /**
     * Rehydrate only as a fallback for a genuinely empty session, and only for an
     * already-paid iwocaPay order. Never overrides an existing session, never acts
     * on non-iwocaPay or not-yet-paid orders.
     */
    public function shouldRehydrate(
        bool $sessionAlreadyPopulated,
        ?string $paymentMethodCode,
        ?string $orderState
    ): bool {
        if ($sessionAlreadyPopulated) {
            return false;
        }

        if (!in_array($paymentMethodCode, self::IWOCAPAY_METHODS, true)) {
            return false;
        }

        return $orderState === self::STATE_PROCESSING;
    }
}
