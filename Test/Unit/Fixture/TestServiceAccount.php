<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Fixture;

/**
 * Generates a throwaway RSA key and service account JSON for tests. Never a real key.
 */
class TestServiceAccount
{
    public const CLIENT_EMAIL = 'dashboard@test-project.iam.gserviceaccount.com';

    private static ?array $pair = null;

    /**
     * @return array{private: string, public: string}
     */
    public static function keyPair(): array
    {
        if (self::$pair === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            $public = openssl_pkey_get_details($key)['key'];
            self::$pair = ['private' => $private, 'public' => $public];
        }

        return self::$pair;
    }

    public static function json(array $overrides = []): string
    {
        return json_encode(array_merge([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'private_key_id' => 'abc123',
            'private_key' => self::keyPair()['private'],
            'client_email' => self::CLIENT_EMAIL,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], $overrides), JSON_PRETTY_PRINT);
    }
}
