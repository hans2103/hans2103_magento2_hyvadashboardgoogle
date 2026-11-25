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

/**
 * Injectable clock so time-dependent code can be tested.
 */
class Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
