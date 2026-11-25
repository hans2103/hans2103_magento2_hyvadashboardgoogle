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
use Hans2103\HyvaDashboardGoogle\Model\Google\GoogleApiException;
use Hans2103\HyvaDashboardGoogle\Model\Google\SearchConsoleClient;
use Hyva\AdminDashboardApi\Api\V1\WidgetContextInterface;
use Hyva\AdminDashboardApi\Api\V1\WidgetInstanceInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Top Google search queries for one store view, from Search Console.
 */
class GscTopQueries extends AbstractGoogleWidget
{
    /** Search Console reports dates in Pacific Time. */
    public const GSC_TIMEZONE = 'America/Los_Angeles';
    /** Search Console data is complete after about 2-3 days. */
    public const GSC_END_OFFSET_DAYS = 3;
    private const IMPRESSIONS_FETCH_ROWS = 250;

    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        Clock $clock,
        private readonly SearchConsoleClient $client,
    ) {
        parent::__construct($config, $storeManager, $clock);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getExtraConfigurableProperties(): array
    {
        return [
            'row_count' => [
                'label' => __('Number of Queries to Display'),
                'input' => [
                    'type' => 'text',
                    'subtype' => 'number',
                    'attributes' => ['value' => 10, 'min' => 1, 'max' => 100],
                ],
            ],
            'order_by' => [
                'label' => __('Sort By'),
                'input' => [
                    'type' => 'select',
                    'options' => [
                        ['value' => 'clicks', 'label' => __('Clicks')],
                        ['value' => 'impressions', 'label' => __('Impressions')],
                    ],
                    'attributes' => ['required' => true, 'value' => 'clicks'],
                ],
            ],
        ];
    }

    public function getDisplayData(WidgetContextInterface $ctx, WidgetInstanceInterface $widgetInstance): mixed
    {
        $storeId = $this->getStoreId($widgetInstance);
        $siteUrl = $this->config->getGscSiteUrl($storeId);
        if ($siteUrl === null) {
            return $this->tableMessage(__('Search Console is not configured for store view %1.', $this->getStoreName($storeId)));
        }

        $rowCount = $this->getIntProperty($widgetInstance, 'row_count', 10, 1, 100);
        $byImpressions = $this->getProperty($widgetInstance, 'order_by') === 'impressions';
        $period = $this->getPeriod($widgetInstance, self::GSC_TIMEZONE, self::GSC_END_OFFSET_DAYS);

        try {
            $rows = $this->client->query($siteUrl, [
                'startDate' => $period->getStartDate(),
                'endDate' => $period->getEndDateInclusive(),
                'dimensions' => ['query'],
                'rowLimit' => $byImpressions ? max($rowCount, self::IMPRESSIONS_FETCH_ROWS) : $rowCount,
                'dataState' => 'final',
            ]);
        } catch (GoogleApiException $e) {
            return $this->tableMessage($e->getSafeMessage());
        }

        if ($rows === []) {
            return $this->tableMessage(__('No Search Console data for this period.'));
        }

        if ($byImpressions) {
            usort($rows, static fn (array $a, array $b): int => ($b['impressions'] ?? 0) <=> ($a['impressions'] ?? 0));
        }

        return [
            'caption' => (string)__(
                'Google search, %1 – %2',
                $period->start->format('d-m-Y'),
                $period->end->modify('-1 day')->format('d-m-Y')
            ),
            'headings' => [(string)__('Query'), (string)__('Clicks'), (string)__('Impressions'), (string)__('CTR'), (string)__('Position')],
            'rows' => array_map(static fn (array $row): array => [
                'values' => [
                    (string)($row['keys'][0] ?? ''),
                    (int)($row['clicks'] ?? 0),
                    (int)($row['impressions'] ?? 0),
                    sprintf('%.1f%%', (float)($row['ctr'] ?? 0) * 100),
                    sprintf('%.1f', (float)($row['position'] ?? 0)),
                ],
            ], array_slice($rows, 0, $rowCount)),
        ];
    }

    public function getTrailingAction(WidgetContextInterface $ctx, ?WidgetInstanceInterface $widgetInstance): array
    {
        $siteUrl = $widgetInstance ? $this->config->getGscSiteUrl($this->getStoreId($widgetInstance)) : null;
        if ($siteUrl === null) {
            return $ctx->getTrailingAction();
        }

        return [
            'href' => 'https://search.google.com/search-console/performance/search-analytics?resource_id=' . rawurlencode($siteUrl),
            'title' => __('Open in Search Console'),
            'target' => '_blank',
        ];
    }
}
