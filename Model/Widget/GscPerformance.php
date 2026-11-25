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
use Hans2103\HyvaDashboardGoogle\Model\Clock;
use Hans2103\HyvaDashboardGoogle\Model\Config;
use Hans2103\HyvaDashboardGoogle\Model\Google\GoogleApiException;
use Hans2103\HyvaDashboardGoogle\Model\Google\SearchConsoleClient;
use Hyva\AdminDashboardApi\Api\V1\WidgetContextInterface;
use Hyva\AdminDashboardApi\Api\V1\WidgetInstanceInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Google search clicks and impressions per day, as ApexCharts line chart options.
 */
class GscPerformance extends AbstractGoogleWidget
{
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        Clock $clock,
        private readonly SearchConsoleClient $client,
    ) {
        parent::__construct($config, $storeManager, $clock);
    }

    public function getDisplayData(WidgetContextInterface $ctx, WidgetInstanceInterface $widgetInstance): mixed
    {
        $storeId = $this->getStoreId($widgetInstance);
        $siteUrl = $this->config->getGscSiteUrl($storeId);
        if ($siteUrl === null) {
            return $this->message(__('Search Console is not configured for store view %1.', $this->getStoreName($storeId)));
        }

        $period = $this->getPeriod($widgetInstance, GscTopQueries::GSC_TIMEZONE, GscTopQueries::GSC_END_OFFSET_DAYS);

        try {
            $rows = $this->client->query($siteUrl, [
                'startDate' => $period->getStartDate(),
                'endDate' => $period->getEndDateInclusive(),
                'dimensions' => ['date'],
                'rowLimit' => 1000,
                'dataState' => 'final',
            ]);
        } catch (GoogleApiException $e) {
            return $this->message($e->getSafeMessage());
        }

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[(string)($row['keys'][0] ?? '')] = $row;
        }

        $categories = $clicks = $impressions = [];
        foreach ($period->getDates() as $date) {
            $categories[] = (new DateTimeImmutable($date))->format('j M');
            $clicks[] = (int)($byDate[$date]['clicks'] ?? 0);
            $impressions[] = (int)($byDate[$date]['impressions'] ?? 0);
        }

        return [
            'chart' => [
                'series' => [
                    ['name' => (string)__('Clicks'), 'data' => $clicks],
                    ['name' => (string)__('Impressions'), 'data' => $impressions],
                ],
                'xaxis' => ['categories' => $categories],
                'yaxis' => [
                    ['title' => ['text' => (string)__('Clicks')]],
                    ['opposite' => true, 'title' => ['text' => (string)__('Impressions')]],
                ],
            ],
            'until' => $period->end->modify('-1 day')->format('d-m-Y'),
        ];
    }
}
