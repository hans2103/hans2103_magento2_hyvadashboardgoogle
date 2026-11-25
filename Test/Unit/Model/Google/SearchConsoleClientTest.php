<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Model\Google;

use Hans2103\HyvaDashboardGoogle\Model\Google\HttpTransport;
use Hans2103\HyvaDashboardGoogle\Model\Google\SearchConsoleClient;
use PHPUnit\Framework\TestCase;

class SearchConsoleClientTest extends TestCase
{
    public function testEncodesSiteUrl(): void
    {
        $transport = $this->createMock(HttpTransport::class);
        $transport->expects($this->once())->method('postJson')
            ->with('https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Aexample.nl/searchAnalytics/query', ['rowLimit' => 5])
            ->willReturn(['rows' => [['keys' => ['a']]]]);

        $this->assertSame([['keys' => ['a']]], (new SearchConsoleClient($transport))->query('sc-domain:example.nl', ['rowLimit' => 5]));
    }

    public function testUrlPrefixPropertyAndNoRows(): void
    {
        $transport = $this->createMock(HttpTransport::class);
        $transport->expects($this->once())->method('postJson')
            ->with('https://www.googleapis.com/webmasters/v3/sites/https%3A%2F%2Fwww.example.nl%2F/searchAnalytics/query')
            ->willReturn(['responseAggregationType' => 'byProperty']);

        $this->assertSame([], (new SearchConsoleClient($transport))->query('https://www.example.nl/', []));
    }
}
