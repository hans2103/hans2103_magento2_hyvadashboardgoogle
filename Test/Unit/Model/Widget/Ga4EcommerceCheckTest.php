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
use Hans2103\HyvaDashboardGoogle\Model\Google\Ga4Client;
use Hans2103\HyvaDashboardGoogle\Model\Google\GoogleApiException;
use Hans2103\HyvaDashboardGoogle\Model\Period;
use Hans2103\HyvaDashboardGoogle\Model\Reconciliation\MagentoOrderSource;
use Hans2103\HyvaDashboardGoogle\Model\Reconciliation\Reconciler;
use Hans2103\HyvaDashboardGoogle\Model\Widget\Ga4EcommerceCheck;
use Hans2103\HyvaDashboardGoogle\Test\Unit\Fixture\WidgetTestHelper;
use Magento\Backend\Model\UrlInterface;
use Magento\Sales\Model\Config\Source\Order\Status as OrderStatusSource;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class Ga4EcommerceCheckTest extends TestCase
{
    use WidgetTestHelper;

    private function storeManagerWithCurrency(string $currency): StoreManagerInterface
    {
        $store = $this->createStub(Store::class);
        $store->method('getName')->willReturn('Example Store NL');
        $store->method('getDefaultCurrencyCode')->willReturn($currency);
        $manager = $this->createStub(StoreManagerInterface::class);
        $manager->method('getStore')->willReturn($store);

        return $manager;
    }

    private function config(?string $propertyId, ?string $hostname = null): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('getGa4PropertyId')->willReturn($propertyId);
        $config->method('getGa4HostnameFilter')->willReturn($hostname);

        return $config;
    }

    private function ga4(array $meta, array $rows, ?callable $assertRequest = null): Ga4Client
    {
        $client = $this->createStub(Ga4Client::class);
        $client->method('getPropertyMeta')->willReturn($meta);
        $client->method('runReport')->willReturnCallback(function (string $id, array $request) use ($rows, $assertRequest) {
            if ($assertRequest) {
                $assertRequest($request);
            }
            return ['rows' => $rows, 'rowCount' => count($rows)];
        });

        return $client;
    }

    private function orders(array $orders, ?callable $assert = null): MagentoOrderSource
    {
        $source = $this->createStub(MagentoOrderSource::class);
        $source->method('getOrders')->willReturnCallback(function (...$args) use ($orders, $assert) {
            if ($assert) {
                $assert(...$args);
            }
            return $orders;
        });

        return $source;
    }

    private function widget(Config $config, Ga4Client $ga4, MagentoOrderSource $orders, string $currency = 'EUR'): Ga4EcommerceCheck
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(fn (string $route, array $params) => '/admin/' . $route . '/order_id/' . $params['order_id']);
        $statuses = $this->createStub(OrderStatusSource::class);
        $statuses->method('toOptionArray')->willReturn([
            ['value' => '', 'label' => '-- Please Select --'],
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'pending_payment', 'label' => 'Pending Payment'],
            ['value' => 'processing', 'label' => 'Processing'],
            ['value' => 'complete', 'label' => 'Complete'],
            ['value' => 'canceled', 'label' => 'Canceled'],
        ]);

        return new Ga4EcommerceCheck(
            $config,
            $this->storeManagerWithCurrency($currency),
            $this->clock(),
            $ga4,
            $orders,
            new Reconciler(),
            $statuses,
            $url
        );
    }

    private static function order(string $id, int $entityId, float $amount): array
    {
        return ['increment_id' => $id, 'entity_id' => $entityId, 'created_at' => '2026-11-20 09:15:00', 'amount' => $amount, 'payment_method' => 'paynl_payment_ideal'];
    }

    private static function row(string $id, float $revenue): array
    {
        return ['dimensionValues' => [['value' => $id]], 'metricValues' => [['value' => '1'], ['value' => (string)$revenue]]];
    }

    public function testBuildsDisplayData(): void
    {
        $widget = $this->widget(
            $this->config('123'),
            $this->ga4(['timeZone' => 'Europe/Amsterdam', 'currencyCode' => 'EUR'], [self::row('100001', 10), self::row('100002', 20)]),
            $this->orders([self::order('100001', 1, 10), self::order('100002', 2, 20), self::order('100003', 3, 30)])
        );

        $data = $widget->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1'], 'period' => 'last_7_days', 'missing_count' => '10']));

        $this->assertArrayNotHasKey('hans2103_no_cache', $data);
        $this->assertSame(['count' => 3, 'revenue' => 60.0], $data['magento']);
        $this->assertSame(['count' => 2, 'revenue' => 30.0], $data['ga4']);
        $this->assertSame(-33.3, $data['count_diff_pct']);
        $this->assertSame('alert', $data['count_severity']);
        $this->assertSame(1, $data['missing_total']);
        $this->assertSame('100003', $data['missing'][0]['increment_id']);
        $this->assertSame('/admin/sales/order/view/order_id/3', $data['missing'][0]['url']);
        $this->assertSame('20-11-2026 10:15', $data['missing'][0]['date']);
        $this->assertSame('18-11-2026 – 24-11-2026', $data['period']);
        $this->assertTrue($data['storefront_only']);
    }

    public function testGa4PurchasesWithoutMagentoOrdersIsAlert(): void
    {
        $widget = $this->widget(
            $this->config('123'),
            $this->ga4(['timeZone' => 'UTC', 'currencyCode' => 'EUR'], [self::row('X1', 10)]),
            $this->orders([])
        );

        $data = $widget->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1']]));

        $this->assertNull($data['count_diff_pct']);
        $this->assertSame('alert', $data['count_severity']);
        $this->assertSame('alert', $data['revenue_severity']);
    }

    public function testOtherRowsReported(): void
    {
        $widget = $this->widget(
            $this->config('123'),
            $this->ga4(['timeZone' => 'UTC', 'currencyCode' => 'EUR'], [self::row('(other)', 5)]),
            $this->orders([])
        );

        $this->assertSame(1, $widget->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1']]))['other_count']);
    }

    public function testMissingListIsLimited(): void
    {
        $widget = $this->widget(
            $this->config('123'),
            $this->ga4(['timeZone' => 'UTC', 'currencyCode' => 'EUR'], []),
            $this->orders([self::order('1', 1, 1), self::order('2', 2, 1), self::order('3', 3, 1)])
        );

        $data = $widget->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1'], 'missing_count' => '2']));

        $this->assertCount(2, $data['missing']);
        $this->assertSame(3, $data['missing_total']);
    }

    public function testHostnameFilterAddedWhenConfigured(): void
    {
        $seen = null;
        $widget = $this->widget(
            $this->config('123', 'www.example.com'),
            $this->ga4(['timeZone' => 'UTC', 'currencyCode' => 'EUR'], [], function (array $r) use (&$seen) {
                $seen = $r;
            }),
            $this->orders([])
        );

        $widget->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1']]));

        $this->assertSame(
            ['filter' => ['fieldName' => 'hostName', 'stringFilter' => ['matchType' => 'EXACT', 'value' => 'www.example.com']]],
            $seen['dimensionFilter']
        );
        $this->assertSame([['name' => 'transactionId']], $seen['dimensions']);
        $this->assertSame([['name' => 'transactions'], ['name' => 'purchaseRevenue']], $seen['metrics']);
    }

    public function testNoHostnameFilterByDefault(): void
    {
        $seen = null;
        $widget = $this->widget(
            $this->config('123'),
            $this->ga4(['timeZone' => 'UTC', 'currencyCode' => 'EUR'], [], function (array $r) use (&$seen) {
                $seen = $r;
            }),
            $this->orders([])
        );

        $widget->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1']]));

        $this->assertArrayNotHasKey('dimensionFilter', $seen);
    }

    public function testCurrencyMismatchSkipsRevenue(): void
    {
        $widget = $this->widget(
            $this->config('123'),
            $this->ga4(['timeZone' => 'UTC', 'currencyCode' => 'USD'], [self::row('1', 10)]),
            $this->orders([self::order('1', 1, 10)]),
            'EUR'
        );

        $data = $widget->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1']]));

        $this->assertTrue($data['currency_mismatch']);
        $this->assertNull($data['revenue_diff_pct']);
        $this->assertNull($data['revenue_severity']);
        $this->assertSame(0.0, $data['count_diff_pct']);
    }

    public function testUsesPropertyTimezoneAndDefaults(): void
    {
        $args = null;
        $widget = $this->widget(
            $this->config('123'),
            $this->ga4(['timeZone' => 'America/New_York', 'currencyCode' => 'EUR'], []),
            $this->orders([], function (...$a) use (&$args) {
                $args = $a;
            })
        );

        $data = $widget->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1']]));

        [$storeId, $period, $statuses, $storefrontOnly, $basis] = $args;
        $this->assertSame(1, $storeId);
        $this->assertInstanceOf(Period::class, $period);
        $this->assertSame('America/New_York', $period->timezone);
        $this->assertSame(['pending', 'processing', 'complete'], $statuses);
        $this->assertTrue($storefrontOnly);
        $this->assertSame('grand_total', $basis);
        $this->assertSame('America/New_York', $data['timezone']);
    }

    public function testExplicitSettingsArePassed(): void
    {
        $args = null;
        $widget = $this->widget(
            $this->config('123'),
            $this->ga4(['timeZone' => 'UTC', 'currencyCode' => 'EUR'], []),
            $this->orders([], function (...$a) use (&$args) {
                $args = $a;
            })
        );

        $widget->getDisplayData($this->ctx(), $this->instance([
            'store_id' => ['1'], 'order_status' => ['complete'], 'storefront_only' => '0', 'revenue_basis' => 'subtotal',
        ]));

        $this->assertSame(['complete'], $args[2]);
        $this->assertFalse($args[3]);
        $this->assertSame('subtotal', $args[4]);
    }

    public function testNotConfigured(): void
    {
        $ga4 = $this->createMock(Ga4Client::class);
        $ga4->expects($this->never())->method('runReport');
        $ga4->expects($this->never())->method('getPropertyMeta');

        $data = $this->widget($this->config(null), $ga4, $this->orders([]))
            ->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1']]));

        $this->assertTrue($data['hans2103_no_cache']);
        $this->assertStringContainsString('Google Analytics 4 is not configured for store view Example Store NL', $data['message']);
    }

    public function testApiError(): void
    {
        $ga4 = $this->createStub(Ga4Client::class);
        $ga4->method('getPropertyMeta')->willThrowException(new GoogleApiException('No access.'));

        $data = $this->widget($this->config('123'), $ga4, $this->orders([]))
            ->getDisplayData($this->ctx(), $this->instance(['store_id' => ['1']]));

        $this->assertSame(['hans2103_no_cache' => true, 'message' => 'No access.'], $data);
    }

    public function testStatusOptionsDefaultExcludesCanceledAndPendingPayment(): void
    {
        $props = $this->widget($this->config(null), $this->createStub(Ga4Client::class), $this->orders([]))
            ->getConfigurableProperties($this->ctx());

        $this->assertSame(['pending', 'processing', 'complete'], $props['order_status']['input']['attributes']['value']);
        $this->assertNotContains('', array_column($props['order_status']['input']['options'], 'value'));
        $this->assertSame('toggle', $props['storefront_only']['input']['type']);
        // The dashboard's toggle template reads "checked" for a new widget, "value" only for a saved one.
        $this->assertTrue($props['storefront_only']['input']['attributes']['checked']);
        $this->assertSame('grand_total', $props['revenue_basis']['input']['attributes']['value']);
    }
}
