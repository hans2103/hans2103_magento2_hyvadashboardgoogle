<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Model\Config\Backend;

use Hans2103\HyvaDashboardGoogle\Model\Google\GoogleApiException;
use Hans2103\HyvaDashboardGoogle\Model\Google\ServiceAccountKey as Key;
use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Framework\Exception\LocalizedException;

/**
 * Validates the pasted service account JSON before it is stored encrypted.
 */
class ServiceAccountKey extends Encrypted
{
    public function beforeSave()
    {
        $value = trim((string)$this->getValue());

        if ($value !== '' && !preg_match('/^\*+$/', $value)) {
            try {
                Key::fromJson($value);
            } catch (GoogleApiException $e) {
                throw new LocalizedException(__($e->getSafeMessage()));
            }
            $this->setValue($value);
        }

        parent::beforeSave();
    }
}
