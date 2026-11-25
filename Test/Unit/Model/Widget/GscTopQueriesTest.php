<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Model\Widget;

use Hans2103\HyvaDashboardGoogle\Model\Config;
use Hans2103\HyvaDashboardGoogle\Model\Google\GoogleApiException;
use Hans2103\HyvaDashboardGoogle\Model\Google\SearchConsoleClient;
use Hans2103\HyvaDashboardGoogle\Model\Widget\GscTopQueries;
use Hans2103\HyvaDashboardGoogle\Test\Unit\Fixture\WidgetTestHelper;
use PHPUnit\Framework\TestCase;

class GscTopQueriesTest extends TestCase
{
    use WidgetTestHelper;

    private function config(?string $site): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('getGscSiteUrl')->willReturn($site);

        return $config;
    }

    private function widget(?string $site, SearchConsoleClient $client): GscTopQueries
    {
        return new GscTopQueries($this->config($site), $this->storeManager(), $this->clock(), $client);
    }

    public function testNotConfiguredSkipsGoogle(): void
    {
        $client = $this->createMock(SearchConsoleClient::class);
        $client->expects($this->never())->method('query');

        $data = $this->widget(null, $client)->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1']]));

        $this->assertTrue($data['hans2103_no_cache']);
        $this->assertStringContainsString('Search Console is not configured for store view Example Store NL', $data['message']);
        $this->assertSame([$data['message']], $data['rows'][0]['values']);
        $this->assertArrayNotHasKey('caption', $data, 'message must be shown once, not as caption and row');
    }

    public function testMapsRows(): void
    {
        $client = $this->createMock(SearchConsoleClient::class);
        $client->expects($this->once())->method('query')
            ->with('sc-domain:example.nl', [
                'startDate' => '2026-10-26',
                'endDate' => '2026-11-22',
                'dimensions' => ['query'],
                'rowLimit' => 10,
                'dataState' => 'final',
            ])
            ->willReturn([['keys' => ['onderdelen'], 'clicks' => 12, 'impressions' => 300, 'ctr' => 0.04, 'position' => 7.234]]);

        $data = $this->widget('sc-domain:example.nl', $client)
            ->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1'], 'period' => 'last_28_days', 'row_count' => '10', 'order_by' => 'clicks']));

        $this->assertArrayNotHasKey('hans2103_no_cache', $data);
        $this->assertSame(['onderdelen', 12, 300, '4.0%', '7.2'], $data['rows'][0]['values']);
        $this->assertCount(5, $data['headings']);
        $this->assertStringContainsString('26-10-2026', $data['caption']);
        $this->assertStringContainsString('22-11-2026', $data['caption']);
    }

    public function testOrderByImpressions(): void
    {
        $client = $this->createStub(SearchConsoleClient::class);
        $client->method('query')->willReturnCallback(function (string $site, array $request) {
            $this->assertSame(250, $request['rowLimit']);
            return [
                ['keys' => ['a'], 'clicks' => 9, 'impressions' => 10, 'ctr' => 0.9, 'position' => 1],
                ['keys' => ['b'], 'clicks' => 1, 'impressions' => 500, 'ctr' => 0.002, 'position' => 9],
                ['keys' => ['c'], 'clicks' => 2, 'impressions' => 100, 'ctr' => 0.02, 'position' => 5],
            ];
        });

        $data = $this->widget('sc-domain:example.nl', $client)
            ->getDisplayData($this->ctx(), $this->instance(['store_id' => '1', 'row_count' => 2, 'order_by' => 'impressions']));

        $this->assertSame(['b', 'c'], array_map(fn ($r) => $r['values'][0], $data['rows']));
    }

    public function testApiErrorBecomesUncachedMessage(): void
    {
        $client = $this->createStub(SearchConsoleClient::class);
        $client->method('query')->willThrowException(new GoogleApiException('Google API quota exceeded, try again later.'));

        $data = $this->widget('sc-domain:example.nl', $client)->getDisplayData($this->ctx(), $this->instance(['store_id' => [1]]));

        $this->assertTrue($data['hans2103_no_cache']);
        $this->assertSame('Google API quota exceeded, try again later.', $data['message']);
    }

    public function testNoDataIsMessageToo(): void
    {
        $client = $this->createStub(SearchConsoleClient::class);
        $client->method('query')->willReturn([]);

        $data = $this->widget('sc-domain:example.nl', $client)->getDisplayData($this->ctx(), $this->instance(['store_id' => [1]]));

        $this->assertStringContainsString('No Search Console data', $data['message']);
    }

    public function testTrailingActionLinksToSearchConsole(): void
    {
        $action = $this->widget('sc-domain:example.nl', $this->createStub(SearchConsoleClient::class))
            ->getTrailingAction($this->ctx(), $this->instance(['store_id' => [1]]));

        $this->assertSame('https://search.google.com/search-console/performance/search-analytics?resource_id=sc-domain%3Aexample.nl', $action['href']);
        $this->assertSame('_blank', $action['target']);
    }

    public function testConfigurablePropertiesHaveSingleStoreScope(): void
    {
        $props = $this->widget(null, $this->createStub(SearchConsoleClient::class))->getConfigurableProperties($this->ctx());

        $this->assertSame('scope', $props['store_id']['input']['type']);
        $this->assertFalse($props['store_id']['input']['attributes']['multiple']);
        $this->assertSame('last_28_days', $props['period']['input']['attributes']['value']);
        $this->assertArrayHasKey('row_count', $props);
        $this->assertArrayHasKey('order_by', $props);
    }
}
