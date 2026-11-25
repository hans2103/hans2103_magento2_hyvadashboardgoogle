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
use Hans2103\HyvaDashboardGoogle\Model\Widget\GscPerformance;
use Hans2103\HyvaDashboardGoogle\Test\Unit\Fixture\WidgetTestHelper;
use PHPUnit\Framework\TestCase;

class GscPerformanceTest extends TestCase
{
    use WidgetTestHelper;

    private function widget(?string $site, SearchConsoleClient $client): GscPerformance
    {
        $config = $this->createStub(Config::class);
        $config->method('getGscSiteUrl')->willReturn($site);

        return new GscPerformance($config, $this->storeManager(), $this->clock(), $client);
    }

    public function testFillsMissingDaysWithZero(): void
    {
        $client = $this->createMock(SearchConsoleClient::class);
        $client->expects($this->once())->method('query')
            ->with('sc-domain:example.nl', $this->callback(fn (array $r) => $r['dimensions'] === ['date']
                && $r['startDate'] === '2026-11-16' && $r['endDate'] === '2026-11-22'))
            ->willReturn([
                ['keys' => ['2026-11-16'], 'clicks' => 5, 'impressions' => 100],
                ['keys' => ['2026-11-17'], 'clicks' => 6, 'impressions' => 110],
                ['keys' => ['2026-11-19'], 'clicks' => 7, 'impressions' => 120],
                ['keys' => ['2026-11-20'], 'clicks' => 8, 'impressions' => 130],
                ['keys' => ['2026-11-22'], 'clicks' => 9, 'impressions' => 140],
            ]);

        $data = $this->widget('sc-domain:example.nl', $client)
            ->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1'], 'period' => 'last_7_days']));

        $this->assertArrayNotHasKey('hans2103_no_cache', $data);
        $this->assertSame(['16 Nov', '17 Nov', '18 Nov', '19 Nov', '20 Nov', '21 Nov', '22 Nov'], $data['chart']['xaxis']['categories']);
        $this->assertSame([5, 6, 0, 7, 8, 0, 9], $data['chart']['series'][0]['data']);
        $this->assertSame([100, 110, 0, 120, 130, 0, 140], $data['chart']['series'][1]['data']);
        $this->assertSame('22-11-2026', $data['until']);
        $this->assertTrue($data['chart']['yaxis'][1]['opposite']);
    }

    public function testNotConfigured(): void
    {
        $client = $this->createMock(SearchConsoleClient::class);
        $client->expects($this->never())->method('query');

        $data = $this->widget(null, $client)->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1']]));

        $this->assertTrue($data['hans2103_no_cache']);
        $this->assertStringContainsString('not configured', $data['message']);
    }

    public function testApiError(): void
    {
        $client = $this->createStub(SearchConsoleClient::class);
        $client->method('query')->willThrowException(new GoogleApiException('Boom.'));

        $data = $this->widget('sc-domain:example.nl', $client)->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1']]));

        $this->assertSame(['hans2103_no_cache' => true, 'message' => 'Boom.'], $data);
    }
}
