<?php

namespace App\Application\GrandpaSson;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpTokenClient implements TokenClientInterface
{
    public function clientCredentialsToken(string $scope): TokenResponse
    {
        $tokenUrl = (string) config('grandpasson.token_url');
        $clientId = (string) config('grandpasson.client_id');
        $clientSecret = (string) config('grandpasson.client_secret');

        if ($tokenUrl === '' || $clientId === '' || $clientSecret === '') {
            throw new RuntimeException('GrandpaSSOn token client is not configured.');
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post($tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => $scope,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('GrandpaSSOn token request failed: HTTP '.$response->status());
        }

        $accessToken = (string) $response->json('access_token', '');
        $expiresIn = (int) $response->json('expires_in', 3600);

        if ($accessToken === '') {
            throw new RuntimeException('GrandpaSSOn token response missing access_token.');
        }

        return new TokenResponse(
            accessToken: $accessToken,
            expiresAtUnix: time() + max(60, $expiresIn),
            tokenType: (string) $response->json('token_type', 'Bearer'),
            scope: $scope,
        );
    }
}
