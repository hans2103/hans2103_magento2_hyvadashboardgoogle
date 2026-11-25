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
 * Compares Magento orders with GA4 transactionId rows. IDs are matched as exact strings.
 */
class Reconciler
{
    private const NOT_SET = ['', '(not set)'];
    /** GA4 groups rows into "(other)" when a report exceeds its cardinality limits. */
    private const OTHER = '(other)';

    /**
     * @param list<array{increment_id: string, entity_id: int, created_at: string, amount: float, payment_method: string}> $magentoOrders
     * @param list<array> $ga4Rows GA4 runReport rows: dimension transactionId, metrics transactions, purchaseRevenue
     */
    public function reconcile(array $magentoOrders, array $ga4Rows): Result
    {
        $ga4Ids = [];
        $ga4Count = 0;
        $ga4Revenue = 0.0;
        $notSet = 0;
        $other = 0;

        foreach ($ga4Rows as $row) {
            $id = (string)($row['dimensionValues'][0]['value'] ?? '');
            $transactions = (int)($row['metricValues'][0]['value'] ?? 0);
            if (trim($id) === self::OTHER) {
                $other += $transactions;
                continue;
            }
            if (in_array(trim($id), self::NOT_SET, true)) {
                $notSet += $transactions;
                continue;
            }
            $ga4Ids[$id] = true;
            $ga4Count += $transactions;
            $ga4Revenue += (float)($row['metricValues'][1]['value'] ?? 0);
        }

        $magentoIds = [];
        $magentoRevenue = 0.0;
        $missing = [];
        foreach ($magentoOrders as $order) {
            $id = (string)$order['increment_id'];
            $magentoIds[$id] = true;
            $magentoRevenue += (float)$order['amount'];
            if (!isset($ga4Ids[$id])) {
                $missing[] = $order;
            }
        }

        usort($missing, static fn (array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));

        $extra = count(array_diff_key($ga4Ids, $magentoIds));
        $magentoCount = count($magentoOrders);

        return new Result(
            $magentoCount,
            round($magentoRevenue, 2),
            $ga4Count,
            round($ga4Revenue, 2),
            $this->diffPct((float)$magentoCount, (float)$ga4Count),
            $this->diffPct($magentoRevenue, $ga4Revenue),
            $missing,
            $extra,
            $notSet,
            $other,
        );
    }

    private function diffPct(float $magento, float $ga4): ?float
    {
        if ($magento == 0.0) {
            return null;
        }

        return round(($ga4 - $magento) / $magento * 100, 1);
    }
}
