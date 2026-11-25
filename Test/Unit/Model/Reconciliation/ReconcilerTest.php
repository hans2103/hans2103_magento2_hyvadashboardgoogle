<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Model\Reconciliation;

use Hans2103\HyvaDashboardGoogle\Model\Reconciliation\Reconciler;
use Hans2103\HyvaDashboardGoogle\Model\Reconciliation\Result;
use PHPUnit\Framework\TestCase;

class ReconcilerTest extends TestCase
{
    private static function order(string $id, float $amount, string $createdAt = '2026-11-20 10:00:00'): array
    {
        return [
            'increment_id' => $id,
            'entity_id' => (int)preg_replace('/\D/', '', $id) ?: 1,
            'created_at' => $createdAt,
            'amount' => $amount,
            'payment_method' => 'paynl_payment_ideal',
        ];
    }

    private static function row(string $id, int $transactions, float $revenue): array
    {
        return [
            'dimensionValues' => [['value' => $id]],
            'metricValues' => [['value' => (string)$transactions], ['value' => (string)$revenue]],
        ];
    }

    public function testAllMatch(): void
    {
        $result = (new Reconciler())->reconcile(
            [self::order('A1', 10), self::order('B2', 20), self::order('C3', 30)],
            [self::row('A1', 1, 10), self::row('B2', 1, 20), self::row('C3', 1, 30)]
        );

        $this->assertSame(3, $result->magentoCount);
        $this->assertSame(60.0, $result->magentoRevenue);
        $this->assertSame(3, $result->ga4Count);
        $this->assertSame(60.0, $result->ga4Revenue);
        $this->assertSame(0.0, $result->countDiffPct);
        $this->assertSame(0.0, $result->revenueDiffPct);
        $this->assertSame([], $result->missingInGa4);
        $this->assertSame(0, $result->extraInGa4Count);
        $this->assertSame(0, $result->notSetCount);
    }

    public function testMissingAndExtra(): void
    {
        $result = (new Reconciler())->reconcile(
            [self::order('A1', 10), self::order('B2', 20), self::order('C3', 30)],
            [self::row('B2', 1, 20), self::row('C3', 1, 30), self::row('X9', 1, 99)]
        );

        $this->assertSame(['A1'], array_column($result->missingInGa4, 'increment_id'));
        $this->assertSame(1, $result->extraInGa4Count);
        $this->assertSame(0.0, $result->countDiffPct);
    }

    public function testMissingNewestFirst(): void
    {
        $result = (new Reconciler())->reconcile(
            [self::order('A1', 10, '2026-11-18 09:00:00'), self::order('B2', 20, '2026-11-20 09:00:00')],
            []
        );

        $this->assertSame(['B2', 'A1'], array_column($result->missingInGa4, 'increment_id'));
    }

    public function testLeadingZerosMatchAsStrings(): void
    {
        $result = (new Reconciler())->reconcile(
            [self::order('000012345', 10), self::order('000012346', 10)],
            [self::row('000012345', 1, 10), self::row('12346', 1, 10)]
        );

        $this->assertSame(['000012346'], array_column($result->missingInGa4, 'increment_id'));
        $this->assertSame(1, $result->extraInGa4Count);
    }

    public function testNotSetCounted(): void
    {
        $result = (new Reconciler())->reconcile(
            [self::order('A1', 10)],
            [self::row('A1', 1, 10), self::row('(not set)', 4, 120), self::row('', 1, 5)]
        );

        $this->assertSame(5, $result->notSetCount);
        $this->assertSame(1, $result->ga4Count);
        $this->assertSame(10.0, $result->ga4Revenue);
    }

    public function testOtherRowCountedSeparately(): void
    {
        $result = (new Reconciler())->reconcile(
            [self::order('A1', 10)],
            [self::row('A1', 1, 10), self::row('(other)', 3, 90)]
        );

        $this->assertSame(3, $result->otherCount);
        $this->assertSame(1, $result->ga4Count);
        $this->assertSame(0, $result->extraInGa4Count);
        $this->assertSame(0, $result->notSetCount);
    }

    public function testEmptyGa4Report(): void
    {
        $result = (new Reconciler())->reconcile([self::order('A1', 10), self::order('B2', 30)], []);

        $this->assertCount(2, $result->missingInGa4);
        $this->assertSame(-100.0, $result->revenueDiffPct);
        $this->assertSame(-100.0, $result->countDiffPct);
    }

    public function testNoMagentoOrdersGivesNullDiff(): void
    {
        $result = (new Reconciler())->reconcile([], [self::row('X1', 1, 10)]);

        $this->assertNull($result->countDiffPct);
        $this->assertNull($result->revenueDiffPct);
        $this->assertSame(1, $result->extraInGa4Count);
    }

    public function testDiffRoundedToOneDecimal(): void
    {
        $result = (new Reconciler())->reconcile(
            [self::order('A1', 100), self::order('B2', 100), self::order('C3', 100)],
            [self::row('A1', 1, 100), self::row('B2', 1, 100)]
        );

        $this->assertSame(-33.3, $result->countDiffPct);
    }

    public function testSeverityThresholds(): void
    {
        $this->assertSame('ok', Result::severity(null));
        $this->assertSame('ok', Result::severity(1.9));
        $this->assertSame('ok', Result::severity(-2.0));
        $this->assertSame('warning', Result::severity(5.0));
        $this->assertSame('warning', Result::severity(-10.0));
        $this->assertSame('alert', Result::severity(10.1));
        $this->assertSame('alert', Result::severity(-100.0));
    }
}
