<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Plugin;

use Hans2103\HyvaDashboardGoogle\Plugin\SkipErrorCachePlugin;
use Hyva\AdminDashboardApi\Api\V1\WidgetInstanceInterface;
use Hyva\AdminDashboardFramework\Model\Cache\Type\AdminDashboard;
use PHPUnit\Framework\TestCase;

class SkipErrorCachePluginTest extends TestCase
{
    public function testNoCacheDataIsNotSaved(): void
    {
        $called = false;
        $result = (new SkipErrorCachePlugin())->aroundSaveWidgetInstanceDisplayData(
            $this->createStub(AdminDashboard::class),
            function () use (&$called) {
                $called = true;
                return true;
            },
            $this->createStub(WidgetInstanceInterface::class),
            ['hans2103_no_cache' => true, 'message' => 'x']
        );

        $this->assertFalse($result);
        $this->assertFalse($called);
    }

    public function testNormalDataIsSaved(): void
    {
        $args = null;
        $instance = $this->createStub(WidgetInstanceInterface::class);
        $result = (new SkipErrorCachePlugin())->aroundSaveWidgetInstanceDisplayData(
            $this->createStub(AdminDashboard::class),
            function (...$a) use (&$args) {
                $args = $a;
                return true;
            },
            $instance,
            ['rows' => [1]]
        );

        $this->assertTrue($result);
        $this->assertSame([$instance, ['rows' => [1]]], $args);
    }
}
