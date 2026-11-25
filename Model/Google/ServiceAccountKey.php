<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Model\Google;

/**
 * Parsed Google service account JSON key.
 */
class ServiceAccountKey
{
    public const DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    private function __construct(
        public readonly string $clientEmail,
        public readonly string $privateKey,
        public readonly string $tokenUri,
    ) {
    }

    /**
     * @throws GoogleApiException
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode(trim($json), true);

        if (!is_array($data)
            || ($data['type'] ?? null) !== 'service_account'
            || !is_string($data['client_email'] ?? null) || $data['client_email'] === ''
            || !is_string($data['private_key'] ?? null) || $data['private_key'] === ''
        ) {
            throw new GoogleApiException(
                (string)__('The service account key is not a valid Google service account JSON key.')
            );
        }

        $tokenUri = is_string($data['token_uri'] ?? null) && $data['token_uri'] !== ''
            ? $data['token_uri']
            : self::DEFAULT_TOKEN_URI;

        return new self($data['client_email'], $data['private_key'], $tokenUri);
    }
}
