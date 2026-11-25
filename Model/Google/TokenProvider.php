<?php

/**
 * Hyvä Dashboard Google for Magento 2
 *
 * @copyright  (C) 2026 Hans Kuijpers <https://hkweb.nl>
 * @license    GPL-2.0-or-later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Hans2103\HyvaDashboardGoogle\Model\Google;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Hans2103\HyvaDashboardGoogle\Model\Clock;
use Hans2103\HyvaDashboardGoogle\Model\Config;
use Magento\Framework\App\CacheInterface;

/**
 * Exchanges a signed service account JWT for an OAuth access token and caches it.
 */
class TokenProvider
{
    public const CACHE_TAG = 'HANS2103_GDASH';
    public const SCOPES = 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly';
    private const EXPIRY_MARGIN = 300;

    public function __construct(
        private readonly Config $config,
        private readonly CacheInterface $cache,
        private readonly ClientInterface $httpClient,
        private readonly JwtSigner $jwtSigner,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws GoogleApiException
     */
    public function getAccessToken(): string
    {
        $key = $this->getKey();
        $cacheKey = 'hans2103_gdash_token_' . sha1($key->clientEmail);

        $cached = (string)$this->cache->load($cacheKey);
        if ($cached !== '') {
            return $cached;
        }

        $assertion = $this->jwtSigner->createAssertion($key, self::SCOPES, $this->clock->now()->getTimestamp());

        try {
            $response = $this->httpClient->request('POST', $key->tokenUri, [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ],
                'http_errors' => false,
                'timeout' => 15,
            ]);
        } catch (GuzzleException) {
            throw new GoogleApiException((string)__('Could not connect to Google to get an access token.'));
        }

        $body = json_decode((string)$response->getBody(), true);
        $token = is_array($body) ? ($body['access_token'] ?? null) : null;

        if ($response->getStatusCode() !== 200 || !is_string($token) || $token === '') {
            $detail = is_array($body) && is_string($body['error_description'] ?? null) ? ' ' . $body['error_description'] : '';
            throw new GoogleApiException((string)__('Google rejected the service account credentials.') . $detail);
        }

        $lifetime = max(60, (int)($body['expires_in'] ?? 3600) - self::EXPIRY_MARGIN);
        $this->cache->save($token, $cacheKey, [self::CACHE_TAG], $lifetime);

        return $token;
    }

    /**
     * @throws GoogleApiException
     */
    public function getKey(): ServiceAccountKey
    {
        $json = $this->config->getServiceAccountJson();
        if ($json === null) {
            throw new GoogleApiException((string)__('No Google service account key configured.'));
        }

        return ServiceAccountKey::fromJson($json);
    }
}
