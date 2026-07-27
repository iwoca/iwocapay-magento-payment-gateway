<?php

declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

/**
 * Whether the legacy order-lookup fallback (matching by increment id, for orders
 * created before the iwocapay_order_id was persisted) is still active.
 *
 * Replaces the former global function isLegacyOrderLookupActive() in
 * Helper/FeatureFlags.php, which was never autoloaded and would fatal when the
 * fallback branch ran.
 */
class LegacyOrderLookup
{
    private const CUTOFF_DATE = '2026-06-02';

    public function isActive(): bool
    {
        return new \DateTimeImmutable() < new \DateTimeImmutable(self::CUTOFF_DATE);
    }
}
