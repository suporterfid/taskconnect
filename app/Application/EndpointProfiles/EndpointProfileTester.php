<?php

namespace App\Application\EndpointProfiles;

use App\Domain\EndpointProfiles\AuthMode;
use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyViolation;
use App\Domain\Secrets\SecretRedactor;
use App\Application\Secrets\SecretService;
use App\Infrastructure\HttpClient\GuzzlePinnedHttpTransport;
use App\Infrastructure\HttpClient\PinnedHttpRequest;
use App\Infrastructure\HttpClient\PinnedHttpTransport;
use App\Infrastructure\Persistence\Eloquent\EndpointProfile;
use App\Infrastructure\Persistence\Eloquent\EndpointTestResult;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Support\Arr;

final class EndpointProfileTester
{
    public function __construct(
        private readonly OutboundPolicy $outboundPolicy,
        private readonly PinnedHttpTransport $transport,
        private readonly SecretService $secretService,
        private readonly SecretRedactor $redactor,
    ) {
    }

    /**
     * @param  array{path?: string|null, body?: string|null}  $options
     */
    public function test(EndpointProfile $profile, User $actor, array $options = []): EndpointTestResult
    {
        $request = $this->buildRequest($profile, $options);
        $secretQueryParams = $this->secretQueryParamNames($profile);

        try {
            $additionalAllowHosts = $this->tenantAllowHosts($profile);
            $validated = $this->outboundPolicy->validateUrl($request['url'], $additionalAllowHosts);
            $this->outboundPolicy->validateHeaders($request['headers']);

            $response = $this->sendWithProfileOptions(
                $profile,
                new PinnedHttpRequest(
                    method: $profile->method,
                    endpoint: $validated,
                    headers: $this->outboundPolicy->sanitizeHeaders($request['headers']),
                    body: $request['body'],
                    verifyTls: $profile->verify_tls,
                    followRedirects: $profile->follow_redirects,
                    connectTimeout: $profile->connect_timeout,
                    totalTimeout: $profile->total_timeout,
                    responseBodyLimit: (int) config('outbound.endpoint_test_response_body_limit', 8192),
                    additionalAllowHosts: $additionalAllowHosts,
                ),
            );

            return $this->storeResult(
                profile: $profile,
                actor: $actor,
                requestUrl: $request['url'],
                requestHeaders: $request['headers'],
                secretQueryParams: $secretQueryParams,
                responseStatus: $response->statusCode,
                responseBody: $response->bodyTruncated,
                transportErrorCode: $response->transportError !== null ? 'transport_error' : null,
            );
        } catch (OutboundPolicyViolation $exception) {
            return $this->storeResult(
                profile: $profile,
                actor: $actor,
                requestUrl: $request['url'],
                requestHeaders: $request['headers'],
                secretQueryParams: $secretQueryParams,
                responseStatus: null,
                responseBody: null,
                transportErrorCode: $exception->reasonCode,
            );
        }
    }

    /**
     * @return list<string>
     */
    private function tenantAllowHosts(EndpointProfile $profile): array
    {
        $tenant = $profile->relationLoaded('tenant')
            ? $profile->tenant
            : $profile->tenant()->first();

        if ($tenant === null) {
            return [];
        }

        return array_values(array_filter(
            (array) ($tenant->outbound_allow_hosts ?? []),
            static fn ($host): bool => is_string($host) && $host !== '',
        ));
    }

    /**
     * @param  array{path?: string|null, body?: string|null}  $options
     * @return array{url: string, headers: array<string, string>, body: ?string}
     */
    private function buildRequest(EndpointProfile $profile, array $options): array
    {
        $url = rtrim($profile->base_url, '/');
        $path = $options['path'] ?? null;

        if ($path !== null && $path !== '') {
            if ($profile->allowed_path_prefix !== null
                && ! str_starts_with($path, $profile->allowed_path_prefix)
            ) {
                throw new OutboundPolicyViolation(
                    'path_not_allowed',
                    'The requested path is outside the allowed prefix.',
                );
            }

            $url .= str_starts_with($path, '/') ? $path : '/'.$path;
        }

        $headers = $profile->visibleHeaders();
        $secretValue = null;

        if ($profile->secret_id !== null && $profile->secret !== null) {
            $secretValue = $this->secretService->decrypt($profile->secret);
        }

        match ($profile->auth_mode) {
            AuthMode::None => null,
            AuthMode::Bearer => $headers['Authorization'] = 'Bearer '.$secretValue,
            AuthMode::Basic => $headers['Authorization'] = 'Basic '.base64_encode((string) $secretValue),
            AuthMode::StaticHeader => $headers[$this->requiredAuthHeaderName($profile)] = (string) $secretValue,
            AuthMode::QueryToken => $url = $this->appendQueryToken(
                $url,
                $this->requiredAuthQueryParam($profile),
                (string) $secretValue,
            ),
        };

        return [
            'url' => $url,
            'headers' => Arr::map($headers, static fn ($value) => (string) $value),
            'body' => $options['body'] ?? null,
        ];
    }

    private function sendWithProfileOptions(EndpointProfile $profile, PinnedHttpRequest $request): \App\Infrastructure\HttpClient\PinnedHttpResponse
    {
        if ($this->transport instanceof GuzzlePinnedHttpTransport) {
            return $this->transport->send($request);
        }

        return $this->transport->send($request);
    }

    private function requiredAuthHeaderName(EndpointProfile $profile): string
    {
        $name = $profile->authConfig()['header_name'] ?? null;

        if (! is_string($name) || $name === '') {
            throw new OutboundPolicyViolation('invalid_auth_config', 'Static header auth requires auth_header_name.');
        }

        return $name;
    }

    private function requiredAuthQueryParam(EndpointProfile $profile): string
    {
        $name = $profile->authConfig()['query_param'] ?? null;

        if (! is_string($name) || $name === '') {
            throw new OutboundPolicyViolation('invalid_auth_config', 'Query token auth requires auth_query_param.');
        }

        return $name;
    }

    private function appendQueryToken(string $url, string $param, string $token): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.rawurlencode($param).'='.rawurlencode($token);
    }

    /**
     * @return list<string>
     */
    private function secretQueryParamNames(EndpointProfile $profile): array
    {
        if ($profile->auth_mode !== AuthMode::QueryToken) {
            return [];
        }

        return [$this->requiredAuthQueryParam($profile)];
    }

    /**
     * @param  array<string, string>  $requestHeaders
     * @param  list<string>  $secretQueryParams
     */
    private function storeResult(
        EndpointProfile $profile,
        User $actor,
        string $requestUrl,
        array $requestHeaders,
        array $secretQueryParams,
        ?int $responseStatus,
        ?string $responseBody,
        ?string $transportErrorCode,
    ): EndpointTestResult {
        return EndpointTestResult::query()->create([
            'tenant_id' => $profile->tenant_id,
            'environment_id' => $profile->environment_id,
            'endpoint_profile_id' => $profile->id,
            'request_url_redacted' => $this->redactor->redactUrl($requestUrl, $secretQueryParams),
            'request_headers_redacted_json' => $this->redactor->redactHeaders($requestHeaders),
            'response_status' => $responseStatus,
            'response_body_truncated' => $responseBody,
            'transport_error_code' => $transportErrorCode,
            'created_by' => $actor->id,
            'created_at' => now(),
        ]);
    }
}
