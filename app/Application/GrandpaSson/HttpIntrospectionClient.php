<?php

namespace App\Application\GrandpaSson;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpIntrospectionClient implements IntrospectionClientInterface
{
    public function introspect(string $token): IntrospectionResult
    {
        $url = (string) config('grandpasson.introspect_url');
        $clientId = (string) config('grandpasson.client_id');
        $clientSecret = (string) config('grandpasson.client_secret');

        if ($url === '') {
            throw new RuntimeException('GrandpaSSOn introspection URL is not configured.');
        }

        $request = Http::asForm()->timeout(10);
        if ($clientId !== '' && $clientSecret !== '') {
            $request = $request->withBasicAuth($clientId, $clientSecret);
        }

        $response = $request->post($url, ['token' => $token]);

        if (! $response->successful()) {
            return new IntrospectionResult(active: false);
        }

        $active = (bool) $response->json('active', false);
        $scopeRaw = $response->json('scope', '');
        $scopes = is_string($scopeRaw)
            ? array_values(array_filter(explode(' ', $scopeRaw)))
            : (is_array($scopeRaw) ? array_map('strval', $scopeRaw) : []);

        $aud = $response->json('aud', []);
        if (is_string($aud)) {
            $audiences = [$aud];
        } elseif (is_array($aud)) {
            $audiences = array_map('strval', $aud);
        } else {
            $audiences = [];
        }

        return new IntrospectionResult(
            active: $active,
            scopes: $scopes,
            audiences: $audiences,
            clientId: $response->json('client_id'),
            subject: $response->json('sub'),
        );
    }
}
