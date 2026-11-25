<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Model\Google;

use RuntimeException;

/**
 * Exception whose message is always safe to show to an admin: it never contains keys or tokens.
 */
class GoogleApiException extends RuntimeException
{
    public function getSafeMessage(): string
    {
        return $this->getMessage();
    }
}
