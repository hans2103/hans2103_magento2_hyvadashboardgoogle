<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Model\Google;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Hans2103\HyvaDashboardGoogle\Model\Config;
use Hans2103\HyvaDashboardGoogle\Model\Google\GoogleApiException;
use Hans2103\HyvaDashboardGoogle\Model\Google\HttpTransport;
use Hans2103\HyvaDashboardGoogle\Model\Google\ServiceAccountKey;
use Hans2103\HyvaDashboardGoogle\Model\Google\TokenProvider;
use Hans2103\HyvaDashboardGoogle\Test\Unit\Fixture\TestServiceAccount;
use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\TestCase;

class HttpTransportTest extends TestCase
{
    private const URL = 'https://analyticsdata.googleapis.com/v1beta/properties/123:runReport';

    /** @var array<string, array{0: string, 1: int|null}> */
    private array $store = [];

    private function cache(): CacheInterface
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn (string $id) => $this->store[$id][0] ?? false);
        $cache->method('save')->willReturnCallback(function ($data, $id, $tags = [], $lifetime = null) {
            $this->store[$id] = [$data, $lifetime];
            return true;
        });

        return $cache;
    }

    private function transport(ClientInterface $http, int $lifetime = 3600): HttpTransport
    {
        $tokens = $this->createStub(TokenProvider::class);
        $tokens->method('getAccessToken')->willReturn('token-xyz');
        $tokens->method('getKey')->willReturn(ServiceAccountKey::fromJson(TestServiceAccount::json()));
        $config = $this->createStub(Config::class);
        $config->method('getResponseCacheLifetime')->willReturn($lifetime);

        return new HttpTransport($tokens, $config, $this->cache(), $http);
    }

    public function testCachesSuccess(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())->method('request')
            ->with('POST', self::URL, $this->callback(fn (array $o) => $o['headers']['Authorization'] === 'Bearer token-xyz' && $o['json'] === ['a' => 1]))
            ->willReturn(new Response(200, [], '{"rows":[1]}'));
        $transport = $this->transport($http);

        $first = $transport->postJson(self::URL, ['a' => 1]);
        $second = $transport->postJson(self::URL, ['a' => 1]);
        $this->assertSame(['rows' => [1]], $first);
        $this->assertSame($first, $second);
        $this->assertSame(3600, array_values($this->store)[0][1]);
    }

    public function testZeroLifetimeDoesNotCache(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->exactly(2))->method('request')->willReturn(new Response(200, [], '{"rows":[]}'));
        $transport = $this->transport($http, 0);

        $transport->postJson(self::URL, []);
        $transport->postJson(self::URL, []);

        $this->assertSame([], $this->store);
    }

    public function testCustomTtlForGet(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('request')->willReturn(new Response(200, [], '{"timeZone":"UTC"}'));

        $this->transport($http)->getJson('https://analyticsadmin.googleapis.com/v1beta/properties/123', 86400);

        $this->assertSame(86400, array_values($this->store)[0][1]);
    }

    public function testCachesErrorFor300Seconds(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('request')->willReturn(new Response(404, [], '{"error":{"message":"Property not found"}}'));

        try {
            $this->transport($http)->postJson(self::URL, []);
            $this->fail('Expected exception');
        } catch (GoogleApiException $e) {
            $this->assertStringContainsString('Property or site not found at Google', $e->getMessage());
            $this->assertStringContainsString('Property not found', $e->getMessage());
        }
        $this->assertSame(300, array_values($this->store)[0][1]);
    }

    public function testCachedErrorRethrowsWithoutHttp(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())->method('request')->willReturn(new Response(429, [], '{}'));
        $transport = $this->transport($http);

        foreach ([1, 2] as $attempt) {
            try {
                $transport->postJson(self::URL, []);
                $this->fail('Expected exception');
            } catch (GoogleApiException $e) {
                $this->assertStringContainsString('quota exceeded', $e->getMessage());
            }
        }
    }

    public function test403MessageContainsClientEmailNotKey(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('request')->willReturn(new Response(403, [], '{"error":{"message":"User does not have sufficient permissions"}}'));

        try {
            $this->transport($http)->postJson(self::URL, []);
            $this->fail('Expected exception');
        } catch (GoogleApiException $e) {
            $this->assertStringContainsString(TestServiceAccount::CLIENT_EMAIL, $e->getMessage());
            $this->assertStringNotContainsString('PRIVATE KEY', $e->getMessage());
            $this->assertStringNotContainsString('token-xyz', $e->getMessage());
        }
    }

    public function testWithoutCacheAlwaysCallsGoogle(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->exactly(2))->method('request')->willReturn(new Response(200, [], '{}'));
        $transport = $this->transport($http)->withoutCache();

        $transport->postJson(self::URL, []);
        $transport->postJson(self::URL, []);
        $this->assertSame([], $this->store);
    }
}
