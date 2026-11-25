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
use Hans2103\HyvaDashboardGoogle\Model\Config;
use Magento\Framework\App\CacheInterface;

/**
 * Authenticated JSON requests to Google APIs, with a response cache and a short error cache
 * so the dashboard never hammers Google, even when the configuration is broken.
 */
class HttpTransport
{
    public const ERROR_TTL = 300;
    private const CACHE_PREFIX = 'hans2103_gdash_resp_';

    public function __construct(
        private readonly TokenProvider $tokenProvider,
        private readonly Config $config,
        private readonly CacheInterface $cache,
        private readonly ClientInterface $httpClient,
        private readonly bool $useCache = true,
    ) {
    }

    /**
     * Same transport, but every call goes to Google and nothing is cached.
     */
    public function withoutCache(): self
    {
        return new self($this->tokenProvider, $this->config, $this->cache, $this->httpClient, false);
    }

    /**
     * @throws GoogleApiException
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function postJson(string $url, array $body, ?int $ttl = null): array
    {
        return $this->request('POST', $url, $body, $ttl);
    }

    /**
     * @throws GoogleApiException
     * @return array<string, mixed>
     */
    public function getJson(string $url, ?int $ttl = null): array
    {
        return $this->request('GET', $url, null, $ttl);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, ?array $body, ?int $ttl): array
    {
        $cacheKey = self::CACHE_PREFIX . sha1($method . $url . json_encode($body));

        if ($this->useCache) {
            $cached = (string)$this->cache->load($cacheKey);
            if ($cached !== '' && is_array($data = json_decode($cached, true))) {
                if (isset($data['__error'])) {
                    throw new GoogleApiException((string)$data['__error']);
                }
                return $data;
            }
        }

        try {
            $data = $this->send($method, $url, $body);
        } catch (GoogleApiException $e) {
            if ($this->useCache) {
                $this->cache->save(
                    json_encode(['__error' => $e->getSafeMessage()]),
                    $cacheKey,
                    [TokenProvider::CACHE_TAG],
                    self::ERROR_TTL
                );
            }
            throw $e;
        }

        $lifetime = $ttl ?? $this->config->getResponseCacheLifetime();
        // A lifetime of 0 means "do not cache"; some backends (Redis) would treat 0 as "never expire".
        if ($this->useCache && $lifetime > 0) {
            $this->cache->save(json_encode($data), $cacheKey, [TokenProvider::CACHE_TAG], $lifetime);
        }

        return $data;
    }

    /**
     * @throws GoogleApiException
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function send(string $method, string $url, ?array $body): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->tokenProvider->getAccessToken(),
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
            'timeout' => 20,
        ];
        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
        } catch (GuzzleException) {
            throw new GoogleApiException((string)__('Google API request failed (connection error).'));
        }

        $status = $response->getStatusCode();
        $data = json_decode((string)$response->getBody(), true);

        if ($status >= 200 && $status < 300) {
            return is_array($data) ? $data : [];
        }

        $detail = is_array($data) && is_string($data['error']['message'] ?? null)
            ? ' (' . $data['error']['message'] . ')'
            : '';

        throw new GoogleApiException($this->statusMessage($status) . $detail);
    }

    private function statusMessage(int $status): string
    {
        return match ($status) {
            401 => (string)__('Google rejected the service account credentials.'),
            403 => (string)__(
                'The service account %1 has no access to this property. Add it in Google Analytics / Search Console.',
                $this->clientEmail()
            ),
            404 => (string)__('Property or site not found at Google: check the configured ID/URL.'),
            429 => (string)__('Google API quota exceeded, try again later.'),
            default => (string)__('Google API request failed (HTTP %1).', $status),
        };
    }

    private function clientEmail(): string
    {
        try {
            return $this->tokenProvider->getKey()->clientEmail;
        } catch (GoogleApiException) {
            return '';
        }
    }
}
