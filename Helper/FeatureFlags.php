<?php

/**
 * Check if legacy fallback lookup is still active (before 2nd June 2026)
 *
 * @return bool
 */
function isLegacyOrderLookupActive(): bool
{
  $currentDate = new \DateTime();
  $cutoffDate = new \DateTime('2026-06-02');

  return $currentDate < $cutoffDate;
}
