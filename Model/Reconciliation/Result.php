<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Model\Reconciliation;

/**
 * Outcome of comparing Magento orders with GA4 transactions.
 */
class Result
{
    /**
     * @param list<array{increment_id: string, entity_id: int, created_at: string, amount: float, payment_method: string}> $missingInGa4
     */
    public function __construct(
        public readonly int $magentoCount,
        public readonly float $magentoRevenue,
        public readonly int $ga4Count,
        public readonly float $ga4Revenue,
        public readonly ?float $countDiffPct,
        public readonly ?float $revenueDiffPct,
        public readonly array $missingInGa4,
        public readonly int $extraInGa4Count,
        public readonly int $notSetCount,
        public readonly int $otherCount = 0,
    ) {
    }

    /**
     * ok: within 2%, warning: within 10%, alert: more than 10% difference.
     */
    public static function severity(?float $pct): string
    {
        if ($pct === null || abs($pct) <= 2.0) {
            return 'ok';
        }

        return abs($pct) <= 10.0 ? 'warning' : 'alert';
    }
}
