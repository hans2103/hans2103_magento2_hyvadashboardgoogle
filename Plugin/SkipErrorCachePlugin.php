<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Plugin;

use Hans2103\HyvaDashboardGoogle\Model\Widget\AbstractGoogleWidget;
use Hyva\AdminDashboardApi\Api\V1\WidgetInstanceInterface;
use Hyva\AdminDashboardFramework\Model\Cache\Type\AdminDashboard;

/**
 * The dashboard caches any non-empty display data for the widget's full cache_lifetime.
 * Error and "not configured" messages must disappear as soon as the problem is fixed,
 * so they are never cached. HttpTransport's 5-minute error cache protects Google's quota.
 */
class SkipErrorCachePlugin
{
    public function aroundSaveWidgetInstanceDisplayData(
        AdminDashboard $subject,
        callable $proceed,
        WidgetInstanceInterface $widgetInstance,
        mixed $displayData,
    ): bool {
        if (is_array($displayData) && !empty($displayData[AbstractGoogleWidget::NO_CACHE_FLAG])) {
            return false;
        }

        return $proceed($widgetInstance, $displayData);
    }
}
