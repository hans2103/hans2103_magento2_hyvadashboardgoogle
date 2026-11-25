<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Test\Unit\Etc;

use DOMDocument;
use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;

/**
 * The dashboard silently drops every widget of a module whose hyva_dashboard_widget.xml fails its XSD.
 */
class WidgetXmlTest extends TestCase
{
    public function testWidgetXmlValidatesAgainstDashboardSchema(): void
    {
        $registrar = new ComponentRegistrar();
        $apiPath = $registrar->getPath(ComponentRegistrar::MODULE, 'Hyva_AdminDashboardApi');
        $this->assertNotNull($apiPath, 'Hyva_AdminDashboardApi must be installed');

        $doc = new DOMDocument();
        $doc->load(dirname(__DIR__, 3) . '/etc/adminhtml/hyva_dashboard_widget.xml');

        libxml_use_internal_errors(true);
        $valid = $doc->schemaValidate($apiPath . '/etc/adminhtml/hyva_dashboard_widget.xsd');
        $errors = array_map(static fn ($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();

        $this->assertTrue($valid, implode("\n", $errors));
        $this->assertSame(3, $doc->getElementsByTagName('widget')->length);
    }

    public function testTemplatesExist(): void
    {
        $doc = new DOMDocument();
        $doc->load(dirname(__DIR__, 3) . '/etc/adminhtml/hyva_dashboard_widget.xml');

        foreach ($doc->getElementsByTagName('template') as $template) {
            [, $path] = explode('::', $template->textContent);
            $this->assertFileExists(dirname(__DIR__, 3) . '/view/adminhtml/templates/' . $path);
        }
    }
}
