<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Model;

use Hans2103\HyvaDashboardGoogle\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testServiceAccountJsonIsReturnedAsLoaded(): void
    {
        // config.xml declares the Encrypted backend model, so Magento's config metadata processor
        // already decrypts the value when it is loaded. Decrypting again would break it.
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn("  {\"type\":\"service_account\"}\n");

        $this->assertSame('{"type":"service_account"}', (new Config($scopeConfig))->getServiceAccountJson());
    }

    public function testEmptyValuesAreNull(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('  ');
        $config = new Config($scopeConfig);

        $this->assertNull($config->getServiceAccountJson());
        $this->assertNull($config->getGa4PropertyId(1));
        $this->assertNull($config->getGscSiteUrl(1));
    }
}
