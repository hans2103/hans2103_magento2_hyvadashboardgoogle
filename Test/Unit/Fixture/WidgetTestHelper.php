<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use Hans2103\HyvaDashboardGoogle\Model\Clock;
use Hyva\AdminDashboardApi\Api\ConfigurationKeys;
use Hyva\AdminDashboardApi\Api\V1\WidgetContextInterface;
use Hyva\AdminDashboardApi\Api\V1\WidgetInstanceInterface;
use Magento\Framework\Phrase;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds stubs shared by the widget tests.
 */
trait WidgetTestHelper
{
    private function ctx(): WidgetContextInterface
    {
        $ctx = $this->createStub(WidgetContextInterface::class);
        $ctx->method('getConfigurableProperties')->willReturn([]);
        $ctx->method('getDisplayProperties')->willReturn([]);
        $ctx->method('getTitle')->willReturn(new Phrase('Widget'));
        $ctx->method('getTrailingAction')->willReturn([]);

        return $ctx;
    }

    private function instance(array $configurable): WidgetInstanceInterface
    {
        $instance = $this->createStub(WidgetInstanceInterface::class);
        $instance->method('getInstanceId')->willReturn(7);
        $instance->method('getPropertyValue')->willReturnCallback(
            fn (string $type, string $name) => $type === ConfigurationKeys::CONFIGURABLE_PROPERTIES
                ? ($configurable[$name] ?? null)
                : null
        );

        return $instance;
    }

    private function storeManager(string $name = 'Example Store NL'): StoreManagerInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getName')->willReturn($name);
        $store->method('getCode')->willReturn('default');
        $manager = $this->createStub(StoreManagerInterface::class);
        $manager->method('getStore')->willReturn($store);

        return $manager;
    }

    private function clock(string $now = '2026-11-25 10:00'): Clock
    {
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn(new DateTimeImmutable($now, new DateTimeZone('UTC')));

        return $clock;
    }
}
