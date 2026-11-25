<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Model\Widget;

use Hans2103\HyvaDashboardGoogle\Model\Clock;
use Hans2103\HyvaDashboardGoogle\Model\Config;
use Hans2103\HyvaDashboardGoogle\Model\Period;
use Hyva\AdminDashboardApi\Api\ConfigurationKeys;
use Hyva\AdminDashboardApi\Api\V1\WidgetContextInterface;
use Hyva\AdminDashboardApi\Api\V1\WidgetInstanceInterface;
use Hyva\AdminDashboardApi\Api\V1\WidgetTypeInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Shared configuration and message handling for the Google widgets.
 */
abstract class AbstractGoogleWidget implements WidgetTypeInterface
{
    /** Display data carrying this flag is never stored in the dashboard cache, see SkipErrorCachePlugin. */
    public const NO_CACHE_FLAG = 'hans2103_no_cache';

    public function __construct(
        protected readonly Config $config,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly Clock $clock,
    ) {
    }

    public function getConfigurableProperties(WidgetContextInterface $ctx): array
    {
        return array_merge(
            $ctx->getConfigurableProperties(),
            ['store_id' => $this->storeProperty(), 'period' => $this->periodProperty()],
            $this->getExtraConfigurableProperties()
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getExtraConfigurableProperties(): array
    {
        return [];
    }

    public function getDisplayProperties(WidgetContextInterface $ctx): array
    {
        return $ctx->getDisplayProperties();
    }

    public function getTitle(WidgetContextInterface $ctx, ?WidgetInstanceInterface $widgetInstance): Phrase
    {
        return $ctx->getTitle();
    }

    public function getTrailingAction(WidgetContextInterface $ctx, ?WidgetInstanceInterface $widgetInstance): array
    {
        return $ctx->getTrailingAction();
    }

    public function isAllowed(WidgetContextInterface $ctx, ?WidgetInstanceInterface $widgetInstance): bool
    {
        return $ctx->isAllowed($widgetInstance);
    }

    public function beforeSave(WidgetContextInterface $ctx, WidgetInstanceInterface $widgetInstance): WidgetInstanceInterface
    {
        return $widgetInstance;
    }

    public function afterSave(WidgetContextInterface $ctx, WidgetInstanceInterface $widgetInstance): WidgetInstanceInterface
    {
        return $widgetInstance;
    }

    protected function getProperty(WidgetInstanceInterface $instance, string $name): mixed
    {
        return $instance->getPropertyValue(ConfigurationKeys::CONFIGURABLE_PROPERTIES, $name);
    }

    /**
     * The scope selector always posts an array; one store view per widget is used.
     */
    protected function getStoreId(WidgetInstanceInterface $instance): int
    {
        $value = $this->getProperty($instance, 'store_id');
        if (is_array($value)) {
            $value = reset($value);
        }

        return (int)$value;
    }

    protected function getStoreName(int $storeId): string
    {
        try {
            return (string)$this->storeManager->getStore($storeId)->getName();
        } catch (NoSuchEntityException) {
            return (string)$storeId;
        }
    }

    protected function getPeriod(WidgetInstanceInterface $instance, string $timezone, int $endOffsetDays): Period
    {
        $option = (string)($this->getProperty($instance, 'period') ?: Period::DEFAULT_OPTION);

        return Period::fromOption($option, $timezone, $endOffsetDays, $this->clock->now());
    }

    protected function getIntProperty(WidgetInstanceInterface $instance, string $name, int $default, int $min, int $max): int
    {
        $value = $this->getProperty($instance, $name);
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int)$value));
    }

    /**
     * @return array<string, mixed>
     */
    protected function storeProperty(): array
    {
        return [
            'label' => __('Store View'),
            'input' => [
                'type' => 'scope',
                'attributes' => [
                    'multiple' => false,
                    'size' => '10',
                    'required' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function periodProperty(): array
    {
        return [
            'label' => __('Period'),
            'input' => [
                'type' => 'select',
                'options' => [
                    ['value' => 'last_7_days', 'label' => __('Last 7 days')],
                    ['value' => 'last_28_days', 'label' => __('Last 28 days')],
                    ['value' => 'last_90_days', 'label' => __('Last 3 months')],
                ],
                'attributes' => [
                    'required' => true,
                    'value' => Period::DEFAULT_OPTION,
                ],
            ],
        ];
    }

    /**
     * Display data for an error or "not configured" state; never cached by the dashboard.
     * @return array<string, mixed>
     */
    protected function message(Phrase|string $text): array
    {
        return [self::NO_CACHE_FLAG => true, 'message' => (string)$text];
    }

    /**
     * Message in the shape the framework's table template renders.
     * @return array<string, mixed>
     */
    protected function tableMessage(Phrase|string $text): array
    {
        $text = (string)$text;

        return $this->message($text) + ['headings' => [], 'rows' => [['values' => [$text]]]];
    }
}
