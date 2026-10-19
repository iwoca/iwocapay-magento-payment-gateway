<?php

declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

/**
 * Pure decision for what the iwocaPay callback should do with an order once
 * iwoca has reported the payment successful.
 *
 * Deliberately framework-free so it can be exercised by the standalone unit
 * suite. The string constants below intentionally duplicate
 * Magento\Sales\Model\Order::STATE_PROCESSING and ::STATE_CANCELED; keep them
 * in sync if those ever change.
 */
class CallbackOutcomePolicy
{
    private const STATE_PROCESSING = 'processing';
    private const STATE_CANCELED = 'canceled';

    /** Order is already paid up; re-running would double-invoice. */
    public const OUTCOME_ALREADY_PROCESSED = 'already_processed';

    /** Happy path: raise the invoice and move the order to processing. */
    public const OUTCOME_INVOICE = 'invoice';

    /**
     * Payment landed on an order Magento had already cancelled. Cancellation is
     * terminal on both sides, so we never reopen or invoice -- we record it and
     * escalate for a refund.
     */
    public const OUTCOME_PAID_AFTER_CANCELLATION = 'paid_after_cancellation';

    /**
     * Not invoiceable for some other reason (complete, closed, on hold, nothing
     * left to invoice). Same inert handling, but worth distinguishing so the
     * integration event says which it was.
     */
    public const OUTCOME_NOT_INVOICEABLE = 'not_invoiceable';

    /**
     * Idempotency is checked first so a replayed callback on a paid order stays a
     * no-op regardless of its invoiceability.
     */
    public function decide(?string $orderState, bool $hasInvoices, bool $canInvoice): string
    {
        if ($orderState === self::STATE_PROCESSING || $hasInvoices) {
            return self::OUTCOME_ALREADY_PROCESSED;
        }

        if ($orderState === self::STATE_CANCELED) {
            return self::OUTCOME_PAID_AFTER_CANCELLATION;
        }

        if (!$canInvoice) {
            return self::OUTCOME_NOT_INVOICEABLE;
        }

        return self::OUTCOME_INVOICE;
    }

    /**
     * True when the outcome means money moved but the order cannot be completed, so the
     * caller must record and escalate rather than send the buyer to the success page.
     *
     * Lives here so a new OUTCOME_* constant only has to be classified in one place.
     */
    public function isUnfulfillable(string $outcome): bool
    {
        return $outcome !== self::OUTCOME_INVOICE && $outcome !== self::OUTCOME_ALREADY_PROCESSED;
    }
}
