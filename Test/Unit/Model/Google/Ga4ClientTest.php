<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Model\Google;

use Hans2103\HyvaDashboardGoogle\Model\Google\Ga4Client;
use Hans2103\HyvaDashboardGoogle\Model\Google\HttpTransport;
use PHPUnit\Framework\TestCase;

class Ga4ClientTest extends TestCase
{
    public function testPaginates(): void
    {
        $transport = $this->createMock(HttpTransport::class);
        $offsets = [];
        $transport->expects($this->exactly(2))->method('postJson')
            ->willReturnCallback(function (string $url, array $body) use (&$offsets) {
                $this->assertSame('https://analyticsdata.googleapis.com/v1beta/properties/123:runReport', $url);
                $this->assertSame(10000, $body['limit']);
                $offsets[] = $body['offset'];
                return ['rows' => [['n' => $body['offset']]], 'rowCount' => 15000];
            });

        $result = (new Ga4Client($transport))->runReport('123', ['dimensions' => []]);

        $this->assertSame([0, 10000], $offsets);
        $this->assertSame([['n' => 0], ['n' => 10000]], $result['rows']);
        $this->assertSame(15000, $result['rowCount']);
    }

    public function testNoRowsKey(): void
    {
        $transport = $this->createStub(HttpTransport::class);
        $transport->method('postJson')->willReturn(['kind' => 'analyticsData#runReport']);

        $result = (new Ga4Client($transport))->runReport('123', []);

        $this->assertSame([], $result['rows']);
        $this->assertSame(0, $result['rowCount']);
    }

    public function testPropertyMeta(): void
    {
        $transport = $this->createMock(HttpTransport::class);
        $transport->expects($this->once())->method('getJson')
            ->with('https://analyticsadmin.googleapis.com/v1beta/properties/123', 86400)
            ->willReturn(['name' => 'properties/123', 'timeZone' => 'Europe/Amsterdam', 'currencyCode' => 'EUR']);

        $this->assertSame(
            ['timeZone' => 'Europe/Amsterdam', 'currencyCode' => 'EUR'],
            (new Ga4Client($transport))->getPropertyMeta('123')
        );
    }
}
