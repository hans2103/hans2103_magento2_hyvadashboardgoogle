<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Model;

use DateTimeImmutable;
use DateTimeZone;
use Hans2103\HyvaDashboardGoogle\Model\Period;
use PHPUnit\Framework\TestCase;

class PeriodTest extends TestCase
{
    public function testLast7DaysEndingYesterday(): void
    {
        $now = new DateTimeImmutable('2026-11-25 10:00', new DateTimeZone('Europe/Amsterdam'));
        $period = Period::lastDays(7, 'Europe/Amsterdam', 1, $now);

        $this->assertSame('2026-11-18', $period->getStartDate());
        $this->assertSame('2026-11-24', $period->getEndDateInclusive());
        $this->assertSame('2026-11-17 23:00:00', $period->getUtcStart());
        $this->assertSame('2026-11-24 23:00:00', $period->getUtcEnd());
        $this->assertSame(7, $period->getDays());
    }

    public function testDstBoundaries(): void
    {
        $now = new DateTimeImmutable('2026-10-30 08:00', new DateTimeZone('Europe/Amsterdam'));
        $period = Period::lastDays(7, 'Europe/Amsterdam', 1, $now);

        $this->assertSame('2026-10-22 22:00:00', $period->getUtcStart());
        $this->assertSame('2026-10-29 23:00:00', $period->getUtcEnd());
        $this->assertSame(7, $period->getDays());
        $this->assertCount(7, $period->getDates());
    }

    public function testNowInOtherTimezoneIsConverted(): void
    {
        // 23:30 UTC on 24 Nov is already 25 Nov in Amsterdam
        $now = new DateTimeImmutable('2026-11-24 23:30', new DateTimeZone('UTC'));
        $period = Period::lastDays(1, 'Europe/Amsterdam', 1, $now);

        $this->assertSame('2026-11-24', $period->getStartDate());
        $this->assertSame('2026-11-24', $period->getEndDateInclusive());
    }

    public function testFromOptionWithGscOffset(): void
    {
        $now = new DateTimeImmutable('2026-11-25 10:00', new DateTimeZone('UTC'));

        $period = Period::fromOption('last_28_days', 'UTC', 3, $now);
        $this->assertSame('2026-10-26', $period->getStartDate());
        $this->assertSame('2026-11-22', $period->getEndDateInclusive());

        $this->assertSame(90, Period::fromOption('last_90_days', 'UTC', 3, $now)->getDays());
        $this->assertSame(28, Period::fromOption('bogus', 'UTC', 3, $now)->getDays());
    }
}
