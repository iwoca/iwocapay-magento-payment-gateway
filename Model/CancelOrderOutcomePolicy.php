<?php

declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

// Framework-free so the standalone unit suite can cover it.
class CancelOrderOutcomePolicy
{
    // Must match iwoca's 400 detail verbatim.
    public const TERMINAL_STATE_ERROR = "Can't cancel order of terminal state";

    public const OUTCOME_CANCELLED = 'cancelled';
    public const OUTCOME_ALREADY_PAID = 'already_paid';
    public const OUTCOME_DEFER_TO_CRON = 'defer_to_cron';

    public function decide(?int $statusCode, ?string $errorDetail): string
    {
        if ($statusCode !== null && $statusCode >= 200 && $statusCode < 300) {
            return self::OUTCOME_CANCELLED;
        }

        if ($statusCode === 400 && $errorDetail === self::TERMINAL_STATE_ERROR) {
            return self::OUTCOME_ALREADY_PAID;
        }

        return self::OUTCOME_DEFER_TO_CRON;
    }

    // Allow-list: an outcome nobody has classified keeps the order rather than risk cancelling a paid one.
    public function shouldCancelLocally(string $outcome): bool
    {
        return in_array($outcome, [self::OUTCOME_CANCELLED, self::OUTCOME_DEFER_TO_CRON], true);
    }
}
