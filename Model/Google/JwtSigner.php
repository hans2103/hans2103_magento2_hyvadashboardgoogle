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
 * Builds the RS256-signed JWT assertion for Google's OAuth 2.0 service account flow.
 */
class JwtSigner
{
    public const LIFETIME = 3600;

    /**
     * @throws GoogleApiException
     */
    public function createAssertion(ServiceAccountKey $key, string $scope, int $now): string
    {
        $segments = [
            $this->encode(['alg' => 'RS256', 'typ' => 'JWT']),
            $this->encode([
                'iss' => $key->clientEmail,
                'scope' => $scope,
                'aud' => $key->tokenUri,
                'iat' => $now,
                'exp' => $now + self::LIFETIME,
            ]),
        ];
        $input = implode('.', $segments);

        $privateKey = openssl_pkey_get_private($key->privateKey);
        if ($privateKey === false || !openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new GoogleApiException((string)__('The private key in the service account key could not be used for signing.'));
        }

        return $input . '.' . $this->base64Url($signature);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        return $this->base64Url(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
