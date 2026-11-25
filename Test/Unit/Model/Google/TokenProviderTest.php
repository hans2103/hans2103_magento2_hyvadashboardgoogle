<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Model\Google;

use DateTimeImmutable;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Hans2103\HyvaDashboardGoogle\Model\Clock;
use Hans2103\HyvaDashboardGoogle\Model\Config;
use Hans2103\HyvaDashboardGoogle\Model\Google\GoogleApiException;
use Hans2103\HyvaDashboardGoogle\Model\Google\JwtSigner;
use Hans2103\HyvaDashboardGoogle\Model\Google\TokenProvider;
use Hans2103\HyvaDashboardGoogle\Test\Unit\Fixture\TestServiceAccount;
use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\TestCase;

class TokenProviderTest extends TestCase
{
    private function clock(): Clock
    {
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('@1700000000'));

        return $clock;
    }

    private function config(?string $json): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('getServiceAccountJson')->willReturn($json);

        return $config;
    }

    public function testUsesCachedToken(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn('cached-token');
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->never())->method('request');

        $provider = new TokenProvider($this->config(TestServiceAccount::json()), $cache, $http, new JwtSigner(), $this->clock());

        $this->assertSame('cached-token', $provider->getAccessToken());
    }

    public function testFetchesAndCaches(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->expects($this->once())->method('save')->with(
            'abc',
            'hans2103_gdash_token_' . sha1(TestServiceAccount::CLIENT_EMAIL),
            [TokenProvider::CACHE_TAG],
            3299
        );
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())->method('request')
            ->with('POST', 'https://oauth2.googleapis.com/token', $this->callback(function (array $options): bool {
                return $options['form_params']['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                    && substr_count($options['form_params']['assertion'], '.') === 2;
            }))
            ->willReturn(new Response(200, [], '{"access_token":"abc","expires_in":3599,"token_type":"Bearer"}'));

        $provider = new TokenProvider($this->config(TestServiceAccount::json()), $cache, $http, new JwtSigner(), $this->clock());

        $this->assertSame('abc', $provider->getAccessToken());
    }

    public function testNoKeyConfigured(): void
    {
        $provider = new TokenProvider(
            $this->config(null),
            $this->createStub(CacheInterface::class),
            $this->createStub(ClientInterface::class),
            new JwtSigner(),
            $this->clock()
        );

        $this->expectException(GoogleApiException::class);
        $this->expectExceptionMessage('No Google service account key configured.');
        $provider->getAccessToken();
    }

    public function testTokenEndpointErrorIsSafe(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $http = $this->createStub(ClientInterface::class);
        $http->method('request')->willReturn(new Response(400, [], '{"error":"invalid_grant","error_description":"Invalid JWT Signature."}'));

        $provider = new TokenProvider($this->config(TestServiceAccount::json()), $cache, $http, new JwtSigner(), $this->clock());

        try {
            $provider->getAccessToken();
            $this->fail('Expected exception');
        } catch (GoogleApiException $e) {
            $this->assertStringContainsString('Google rejected the service account credentials', $e->getMessage());
            $this->assertStringContainsString('Invalid JWT Signature.', $e->getMessage());
            $this->assertStringNotContainsString('PRIVATE KEY', $e->getMessage());
        }
    }
}
