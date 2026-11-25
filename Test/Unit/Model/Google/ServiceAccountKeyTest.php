<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Model\Google;

use Hans2103\HyvaDashboardGoogle\Model\Google\GoogleApiException;
use Hans2103\HyvaDashboardGoogle\Model\Google\ServiceAccountKey;
use Hans2103\HyvaDashboardGoogle\Test\Unit\Fixture\TestServiceAccount;
use PHPUnit\Framework\TestCase;

class ServiceAccountKeyTest extends TestCase
{
    public function testParsesKeyWithWhitespace(): void
    {
        $key = ServiceAccountKey::fromJson("\n  " . TestServiceAccount::json() . "  \n");

        $this->assertSame(TestServiceAccount::CLIENT_EMAIL, $key->clientEmail);
        $this->assertSame(TestServiceAccount::keyPair()['private'], $key->privateKey);
        $this->assertSame('https://oauth2.googleapis.com/token', $key->tokenUri);
    }

    public function testDefaultsTokenUri(): void
    {
        $json = json_decode(TestServiceAccount::json(), true);
        unset($json['token_uri']);

        $this->assertSame('https://oauth2.googleapis.com/token', ServiceAccountKey::fromJson(json_encode($json))->tokenUri);
    }

    public function testRejectsNonServiceAccount(): void
    {
        try {
            ServiceAccountKey::fromJson(TestServiceAccount::json(['type' => 'authorized_user']));
            $this->fail('Expected exception');
        } catch (GoogleApiException $e) {
            $this->assertStringNotContainsString('PRIVATE KEY', $e->getMessage());
            $this->assertStringContainsString('not a valid Google service account', $e->getSafeMessage());
        }
    }

    public function testRejectsInvalidJson(): void
    {
        $this->expectException(GoogleApiException::class);
        ServiceAccountKey::fromJson('{not json');
    }

    public function testRejectsMissingPrivateKey(): void
    {
        $this->expectException(GoogleApiException::class);
        ServiceAccountKey::fromJson(TestServiceAccount::json(['private_key' => '']));
    }
}
