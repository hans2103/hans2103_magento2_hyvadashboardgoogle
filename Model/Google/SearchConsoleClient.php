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
 * Google Search Console searchAnalytics.query.
 */
class SearchConsoleClient
{
    private const URL = 'https://www.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query';

    public function __construct(
        private readonly HttpTransport $transport,
    ) {
    }

    /**
     * @return list<array>
     * @throws GoogleApiException
     * @param array<string, mixed> $request
     */
    public function query(string $siteUrl, array $request): array
    {
        $response = $this->transport->postJson(sprintf(self::URL, rawurlencode($siteUrl)), $request);

        return $response['rows'] ?? [];
    }
}
