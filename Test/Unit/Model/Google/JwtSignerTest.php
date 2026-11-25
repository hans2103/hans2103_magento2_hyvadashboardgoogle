<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Model\Google;

use Hans2103\HyvaDashboardGoogle\Model\Google\JwtSigner;
use Hans2103\HyvaDashboardGoogle\Model\Google\ServiceAccountKey;
use Hans2103\HyvaDashboardGoogle\Test\Unit\Fixture\TestServiceAccount;
use PHPUnit\Framework\TestCase;

class JwtSignerTest extends TestCase
{
    private static function b64decode(string $part): string
    {
        return base64_decode(strtr($part, '-_', '+/') . str_repeat('=', (4 - strlen($part) % 4) % 4));
    }

    public function testAssertionVerifies(): void
    {
        $key = ServiceAccountKey::fromJson(TestServiceAccount::json());
        $scope = 'https://www.googleapis.com/auth/analytics.readonly';

        $jwt = (new JwtSigner())->createAssertion($key, $scope, 1_700_000_000);
        $parts = explode('.', $jwt);

        $this->assertCount(3, $parts);
        $this->assertStringNotContainsString('=', $jwt);
        $this->assertSame(['alg' => 'RS256', 'typ' => 'JWT'], json_decode(self::b64decode($parts[0]), true));
        $this->assertSame([
            'iss' => TestServiceAccount::CLIENT_EMAIL,
            'scope' => $scope,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => 1_700_000_000,
            'exp' => 1_700_003_600,
        ], json_decode(self::b64decode($parts[1]), true));
        $this->assertSame(1, openssl_verify(
            $parts[0] . '.' . $parts[1],
            self::b64decode($parts[2]),
            TestServiceAccount::keyPair()['public'],
            OPENSSL_ALGO_SHA256
        ));
    }
}
