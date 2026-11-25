<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Model\Google;

/**
 * Google Analytics Data API (runReport) and Admin API (property metadata).
 */
class Ga4Client
{
    public const PAGE_SIZE = 10000;
    private const DATA_URL = 'https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport';
    private const ADMIN_URL = 'https://analyticsadmin.googleapis.com/v1beta/properties/%s';
    private const META_TTL = 86400;

    public function __construct(
        private readonly HttpTransport $transport,
    ) {
    }

    /**
     * Runs a report and follows pagination.
     *
     * @return array{rows: list<array>, rowCount: int}
     * @throws GoogleApiException
     * @param array<string, mixed> $request
     */
    public function runReport(string $propertyId, array $request): array
    {
        $url = sprintf(self::DATA_URL, rawurlencode($propertyId));
        $rows = [];
        $rowCount = 0;
        $offset = 0;

        do {
            $response = $this->transport->postJson($url, $request + ['limit' => self::PAGE_SIZE, 'offset' => $offset]);
            $pageRows = $response['rows'] ?? [];
            array_push($rows, ...$pageRows);
            $rowCount = (int)($response['rowCount'] ?? count($rows));
            $offset += self::PAGE_SIZE;
        } while ($pageRows !== [] && $offset < $rowCount);

        return ['rows' => $rows, 'rowCount' => $rowCount];
    }

    /**
     * @return array{timeZone: string, currencyCode: string}
     * @throws GoogleApiException
     */
    public function getPropertyMeta(string $propertyId): array
    {
        $response = $this->transport->getJson(sprintf(self::ADMIN_URL, rawurlencode($propertyId)), self::META_TTL);

        return [
            'timeZone' => (string)($response['timeZone'] ?? 'UTC'),
            'currencyCode' => (string)($response['currencyCode'] ?? ''),
        ];
    }
}
