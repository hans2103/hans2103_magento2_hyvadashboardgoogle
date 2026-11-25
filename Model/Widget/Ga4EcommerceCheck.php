<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Model\Widget;

use DateTimeImmutable;
use DateTimeZone;
use Hans2103\HyvaDashboardGoogle\Model\Clock;
use Hans2103\HyvaDashboardGoogle\Model\Config;
use Hans2103\HyvaDashboardGoogle\Model\Google\Ga4Client;
use Hans2103\HyvaDashboardGoogle\Model\Google\GoogleApiException;
use Hans2103\HyvaDashboardGoogle\Model\Reconciliation\MagentoOrderSource;
use Hans2103\HyvaDashboardGoogle\Model\Reconciliation\Reconciler;
use Hans2103\HyvaDashboardGoogle\Model\Reconciliation\Result;
use Hyva\AdminDashboardApi\Api\V1\WidgetContextInterface;
use Hyva\AdminDashboardApi\Api\V1\WidgetInstanceInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Sales\Model\Config\Source\Order\Status as OrderStatusSource;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Compares Magento orders with GA4 purchases for one store view, up to and including yesterday.
 */
class Ga4EcommerceCheck extends AbstractGoogleWidget
{
    /** GA4 needs up to ~24 hours to process purchases; today is never compared. */
    public const END_OFFSET_DAYS = 1;
    /** No browser purchase event for these: cancelled orders and abandoned iDEAL/card payments. */
    public const EXCLUDED_DEFAULT_STATUSES = ['canceled', 'pending_payment'];

    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        Clock $clock,
        private readonly Ga4Client $ga4Client,
        private readonly MagentoOrderSource $orderSource,
        private readonly Reconciler $reconciler,
        private readonly OrderStatusSource $orderStatusSource,
        private readonly UrlInterface $backendUrl,
    ) {
        parent::__construct($config, $storeManager, $clock);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getExtraConfigurableProperties(): array
    {
        $statusOptions = $this->getStatusOptions();
        $statusCount = count($statusOptions);

        return [
            'order_status' => [
                'label' => __('Order Statuses to Compare'),
                'input' => [
                    'type' => 'select',
                    'options' => $statusOptions,
                    'attributes' => [
                        'multiple' => true,
                        'size' => min(10, max(1, $statusCount)),
                        'value' => $this->getDefaultStatuses(),
                    ],
                ],
            ],
            'storefront_only' => [
                'label' => __('Only Orders Placed in the Storefront'),
                'input' => [
                    'type' => 'toggle',
                    // New widgets: the toggle template reads "checked"; saved widgets get their stored value.
                    'attributes' => ['checked' => true],
                ],
            ],
            'revenue_basis' => [
                'label' => __('Magento Amount to Compare with the GA4 Purchase Value'),
                'input' => [
                    'type' => 'select',
                    'options' => [
                        ['value' => 'grand_total', 'label' => __('Grand total (incl. tax and shipping)')],
                        ['value' => 'subtotal_incl_tax', 'label' => __('Subtotal incl. tax')],
                        ['value' => 'subtotal', 'label' => __('Subtotal excl. tax')],
                    ],
                    'attributes' => ['required' => true, 'value' => 'grand_total'],
                ],
            ],
            'missing_count' => [
                'label' => __('Number of Missing Orders to List'),
                'input' => [
                    'type' => 'text',
                    'subtype' => 'number',
                    'attributes' => ['value' => 10, 'min' => 0, 'max' => 50],
                ],
            ],
        ];
    }

    public function getDisplayData(WidgetContextInterface $ctx, WidgetInstanceInterface $widgetInstance): mixed
    {
        $storeId = $this->getStoreId($widgetInstance);
        $propertyId = $this->config->getGa4PropertyId($storeId);
        if ($propertyId === null) {
            return $this->message(__('Google Analytics 4 is not configured for store view %1.', $this->getStoreName($storeId)));
        }

        try {
            $meta = $this->ga4Client->getPropertyMeta($propertyId);
            $timezone = $this->validTimezone($meta['timeZone']);
            $period = $this->getPeriod($widgetInstance, $timezone, self::END_OFFSET_DAYS);

            $request = [
                'dateRanges' => [['startDate' => $period->getStartDate(), 'endDate' => $period->getEndDateInclusive()]],
                'dimensions' => [['name' => 'transactionId']],
                'metrics' => [['name' => 'transactions'], ['name' => 'purchaseRevenue']],
            ];
            $hostname = $this->config->getGa4HostnameFilter($storeId);
            if ($hostname !== null) {
                $request['dimensionFilter'] = [
                    'filter' => ['fieldName' => 'hostName', 'stringFilter' => ['matchType' => 'EXACT', 'value' => $hostname]],
                ];
            }
            $ga4Rows = $this->ga4Client->runReport($propertyId, $request)['rows'];
        } catch (GoogleApiException $e) {
            return $this->message($e->getSafeMessage());
        }

        $statuses = $this->getProperty($widgetInstance, 'order_status');
        $statuses = is_array($statuses) && $statuses !== [] ? array_values(array_map('strval', $statuses)) : $this->getDefaultStatuses();
        $storefrontOnly = $this->getProperty($widgetInstance, 'storefront_only');
        $storefrontOnly = $storefrontOnly === null || $storefrontOnly === '' || (bool)(int)$storefrontOnly;
        $basis = (string)($this->getProperty($widgetInstance, 'revenue_basis') ?: 'grand_total');

        $orders = $this->orderSource->getOrders($storeId, $period, $statuses, $storefrontOnly, $basis);
        $result = $this->reconciler->reconcile($orders, $ga4Rows);

        $storeCurrency = $this->getStoreCurrency($storeId);
        $currencyMismatch = $meta['currencyCode'] !== '' && $storeCurrency !== '' && $meta['currencyCode'] !== $storeCurrency;
        $revenueDiff = $currencyMismatch ? null : $result->revenueDiffPct;
        // GA4 purchases while Magento has no matching orders at all: no percentage, but certainly not "ok".
        $onlyGa4 = $result->magentoCount === 0 && $result->ga4Count > 0;
        $missingCount = $this->getIntProperty($widgetInstance, 'missing_count', 10, 0, 50);

        return [
            'period' => $period->start->format('d-m-Y') . ' – ' . $period->end->modify('-1 day')->format('d-m-Y'),
            'timezone' => $timezone,
            'currency' => $storeCurrency ?: $meta['currencyCode'],
            'ga4_currency' => $meta['currencyCode'],
            'currency_mismatch' => $currencyMismatch,
            'storefront_only' => $storefrontOnly,
            'magento' => ['count' => $result->magentoCount, 'revenue' => $result->magentoRevenue],
            'ga4' => ['count' => $result->ga4Count, 'revenue' => $result->ga4Revenue],
            'count_diff_pct' => $result->countDiffPct,
            'revenue_diff_pct' => $revenueDiff,
            'count_severity' => $onlyGa4 ? 'alert' : Result::severity($result->countDiffPct),
            'revenue_severity' => $currencyMismatch ? null : ($onlyGa4 ? 'alert' : Result::severity($revenueDiff)),
            'missing' => array_map(
                fn (array $order): array => [
                    'increment_id' => $order['increment_id'],
                    'date' => $this->formatDate($order['created_at'], $timezone),
                    'amount' => $order['amount'],
                    'payment_method' => $order['payment_method'],
                    'url' => $this->backendUrl->getUrl('sales/order/view', ['order_id' => $order['entity_id']]),
                ],
                array_slice($result->missingInGa4, 0, $missingCount)
            ),
            'missing_total' => count($result->missingInGa4),
            'extra_count' => $result->extraInGa4Count,
            'not_set_count' => $result->notSetCount,
            'other_count' => $result->otherCount,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function getStatusOptions(): array
    {
        $options = [];
        foreach ($this->orderStatusSource->toOptionArray() as $option) {
            if ((string)($option['value'] ?? '') !== '') {
                $options[] = ['value' => (string)$option['value'], 'label' => (string)$option['label']];
            }
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function getDefaultStatuses(): array
    {
        return array_values(array_diff(array_column($this->getStatusOptions(), 'value'), self::EXCLUDED_DEFAULT_STATUSES));
    }

    private function getStoreCurrency(int $storeId): string
    {
        try {
            $store = $this->storeManager->getStore($storeId);
            return method_exists($store, 'getDefaultCurrencyCode') ? (string)$store->getDefaultCurrencyCode() : '';
        } catch (Throwable) {
            return '';
        }
    }

    private function validTimezone(string $timezone): string
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC';
    }

    private function formatDate(string $utc, string $timezone): string
    {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone($timezone))
            ->format('d-m-Y H:i');
    }
}
