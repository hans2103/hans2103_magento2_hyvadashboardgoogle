<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Console\Command;

use Hans2103\HyvaDashboardGoogle\Console\Command\TestConnection;
use Hans2103\HyvaDashboardGoogle\Model\Config;
use Hans2103\HyvaDashboardGoogle\Model\Google\GoogleApiException;
use Hans2103\HyvaDashboardGoogle\Model\Google\HttpTransport;
use Hans2103\HyvaDashboardGoogle\Model\Google\ServiceAccountKey;
use Hans2103\HyvaDashboardGoogle\Model\Google\TokenProvider;
use Hans2103\HyvaDashboardGoogle\Test\Unit\Fixture\TestServiceAccount;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class TestConnectionTest extends TestCase
{
    private function store(int $id, string $code): StoreInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn($id);
        $store->method('getCode')->willReturn($code);

        return $store;
    }

    private function command(TokenProvider $tokens, HttpTransport $transport, array $ga4 = [], array $gsc = []): CommandTester
    {
        $config = $this->createStub(Config::class);
        $config->method('getGa4PropertyId')->willReturnCallback(fn (int $id) => $ga4[$id] ?? null);
        $config->method('getGscSiteUrl')->willReturnCallback(fn (int $id) => $gsc[$id] ?? null);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStores')->willReturn([$this->store(1, 'nl'), $this->store(2, 'b2b')]);

        return new CommandTester(new TestConnection($tokens, $transport, $config, $stores));
    }

    public function testNoKeyFailsWithExitCode1(): void
    {
        $tokens = $this->createStub(TokenProvider::class);
        $tokens->method('getKey')->willThrowException(new GoogleApiException('No Google service account key configured.'));
        $transport = $this->createMock(HttpTransport::class);
        $transport->expects($this->never())->method('withoutCache');

        $tester = $this->command($tokens, $transport);

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('[FAIL] Service account key: No Google service account key configured.', $tester->getDisplay());
    }

    public function testChecksEveryStoreAndNeverPrintsSecrets(): void
    {
        $tokens = $this->createStub(TokenProvider::class);
        $tokens->method('getKey')->willReturn(ServiceAccountKey::fromJson(TestServiceAccount::json()));
        $tokens->method('getAccessToken')->willReturn('ya29.secret-token');
        $uncached = $this->createStub(HttpTransport::class);
        $uncached->method('getJson')->willReturn(['timeZone' => 'Europe/Amsterdam', 'currencyCode' => 'EUR']);
        $uncached->method('postJson')->willReturnCallback(function (string $url) {
            if (str_contains($url, 'webmasters')) {
                throw new GoogleApiException('The service account has no access to this property.');
            }
            return ['rows' => [], 'rowCount' => 0];
        });
        $transport = $this->createStub(HttpTransport::class);
        $transport->method('withoutCache')->willReturn($uncached);

        $tester = $this->command($tokens, $transport, [1 => '123'], [1 => 'sc-domain:example.nl']);
        $exit = $tester->execute([]);
        $out = $tester->getDisplay();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('[OK] Service account key: ' . TestServiceAccount::CLIENT_EMAIL, $out);
        $this->assertStringContainsString('[OK] Access token', $out);
        $this->assertStringContainsString('[OK] nl GA4 property 123 (Europe/Amsterdam, EUR)', $out);
        $this->assertStringContainsString('[FAIL] nl Search Console sc-domain:example.nl: The service account has no access', $out);
        $this->assertStringContainsString('[SKIP] b2b GA4 property: not configured', $out);
        $this->assertStringNotContainsString('PRIVATE KEY', $out);
        $this->assertStringNotContainsString('ya29.secret-token', $out);
    }

    public function testStoreOptionLimitsAndAllOkExitsZero(): void
    {
        $tokens = $this->createStub(TokenProvider::class);
        $tokens->method('getKey')->willReturn(ServiceAccountKey::fromJson(TestServiceAccount::json()));
        $tokens->method('getAccessToken')->willReturn('t');
        $uncached = $this->createStub(HttpTransport::class);
        $uncached->method('getJson')->willReturn(['timeZone' => 'UTC', 'currencyCode' => 'EUR']);
        $uncached->method('postJson')->willReturn(['rows' => []]);
        $transport = $this->createStub(HttpTransport::class);
        $transport->method('withoutCache')->willReturn($uncached);

        $tester = $this->command($tokens, $transport, [2 => '456'], [2 => 'https://b2b.example.com/']);

        $this->assertSame(0, $tester->execute(['--store' => 'b2b']));
        $this->assertStringNotContainsString(' nl ', $tester->getDisplay());
        $this->assertStringContainsString('[OK] b2b Search Console https://b2b.example.com/', $tester->getDisplay());
    }
}
