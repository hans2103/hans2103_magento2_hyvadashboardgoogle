<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Model;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A fixed range of whole days in a timezone: [start 00:00, end 00:00).
 */
class Period
{
    public const OPTIONS = [
        'last_7_days' => 7,
        'last_28_days' => 28,
        'last_90_days' => 90,
    ];
    public const DEFAULT_OPTION = 'last_28_days';

    private function __construct(
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end,
        public readonly string $timezone,
        private readonly int $days,
    ) {
    }

    /**
     * @param int $endOffsetDays 1 = the period ends with yesterday, 3 = three days ago
     */
    public static function lastDays(int $days, string $timezone, int $endOffsetDays, ?DateTimeImmutable $now = null): self
    {
        $tz = new DateTimeZone($timezone);
        $today = ($now ?? new DateTimeImmutable())->setTimezone($tz)->setTime(0, 0);
        $end = $today->modify(sprintf('-%d days', max(0, $endOffsetDays - 1)));
        $start = $end->modify(sprintf('-%d days', $days));

        return new self($start, $end, $timezone, $days);
    }

    public static function fromOption(string $option, string $timezone, int $endOffsetDays, ?DateTimeImmutable $now = null): self
    {
        $days = self::OPTIONS[$option] ?? self::OPTIONS[self::DEFAULT_OPTION];

        return self::lastDays($days, $timezone, $endOffsetDays, $now);
    }

    public function getStartDate(): string
    {
        return $this->start->format('Y-m-d');
    }

    public function getEndDateInclusive(): string
    {
        return $this->end->modify('-1 day')->format('Y-m-d');
    }

    public function getUtcStart(): string
    {
        return $this->start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function getUtcEnd(): string
    {
        return $this->end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function getDays(): int
    {
        return $this->days;
    }

    /**
     * Every day in the period as Y-m-d, oldest first.
     *
     * @return list<string>
     */
    public function getDates(): array
    {
        $dates = [];
        for ($day = $this->start; $day < $this->end; $day = $day->modify('+1 day')) {
            $dates[] = $day->format('Y-m-d');
        }

        return $dates;
    }
}
