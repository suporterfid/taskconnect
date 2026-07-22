<?php

namespace App\Application\GrandpaSson;

use Illuminate\Support\Facades\Cache;

/**
 * Caches client-credentials tokens until near expiry (Q5).
 */
final class CachedTokenClient implements TokenClientInterface
{
    public function __construct(
        private readonly TokenClientInterface $inner,
    ) {
    }

    public function clientCredentialsToken(string $scope): TokenResponse
    {
        $cacheKey = 'grandpasson:cc:'.hash('sha256', $scope);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)
            && isset($cached['access_token'], $cached['expires_at'])
            && is_string($cached['access_token'])
            && is_int($cached['expires_at'])
        ) {
            $token = new TokenResponse(
                accessToken: $cached['access_token'],
                expiresAtUnix: $cached['expires_at'],
                scope: $scope,
            );
            $skew = (int) config('grandpasson.token_refresh_skew_seconds', 60);
            if (! $token->isExpired(time(), $skew)) {
                return $token;
            }
        }

        $fresh = $this->inner->clientCredentialsToken($scope);
        $ttl = max(30, $fresh->expiresAtUnix - time() - (int) config('grandpasson.token_refresh_skew_seconds', 60));
        Cache::put($cacheKey, [
            'access_token' => $fresh->accessToken,
            'expires_at' => $fresh->expiresAtUnix,
        ], $ttl);

        return $fresh;
    }
}
