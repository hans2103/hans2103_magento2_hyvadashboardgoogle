<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to the module configuration.
 */
class Config
{
    public const XML_PATH_SERVICE_ACCOUNT_KEY = 'hyva_dashboard_google/general/service_account_key';
    public const XML_PATH_RESPONSE_CACHE_LIFETIME = 'hyva_dashboard_google/general/response_cache_lifetime';
    public const XML_PATH_GA4_PROPERTY_ID = 'hyva_dashboard_google/ga4/property_id';
    public const XML_PATH_GA4_HOSTNAME_FILTER = 'hyva_dashboard_google/ga4/hostname_filter';
    public const XML_PATH_GSC_SITE_URL = 'hyva_dashboard_google/gsc/site_url';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    /**
     * Service account JSON, or null when not configured.
     *
     * Stored encrypted; etc/config.xml declares the Encrypted backend model for this path, so Magento's
     * config metadata processor decrypts it on load and the value arrives here as plain JSON.
     */
    public function getServiceAccountJson(): ?string
    {
        $value = trim((string)$this->scopeConfig->getValue(self::XML_PATH_SERVICE_ACCOUNT_KEY));

        return $value === '' ? null : $value;
    }

    public function getResponseCacheLifetime(): int
    {
        return max(0, (int)$this->scopeConfig->getValue(self::XML_PATH_RESPONSE_CACHE_LIFETIME));
    }

    public function getGa4PropertyId(int $storeId): ?string
    {
        return $this->getStoreValue(self::XML_PATH_GA4_PROPERTY_ID, $storeId);
    }

    public function getGa4HostnameFilter(int $storeId): ?string
    {
        return $this->getStoreValue(self::XML_PATH_GA4_HOSTNAME_FILTER, $storeId);
    }

    public function getGscSiteUrl(int $storeId): ?string
    {
        return $this->getStoreValue(self::XML_PATH_GSC_SITE_URL, $storeId);
    }

    private function getStoreValue(string $path, int $storeId): ?string
    {
        $value = trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId));

        return $value === '' ? null : $value;
    }
}
