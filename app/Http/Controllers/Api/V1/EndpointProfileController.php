<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Audit\AuditLogger;
use App\Application\EndpointProfiles\EndpointProfileTester;
use App\Domain\EndpointProfiles\AuthMode;
use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\EndpointProfileResource;
use App\Http\Resources\EndpointTestResultResource;
use App\Infrastructure\Persistence\Eloquent\EndpointProfile;
use App\Infrastructure\Persistence\Eloquent\Secret;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class EndpointProfileController extends Controller
{
    use ResolvesTenantContext;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly EndpointProfileTester $profileTester,
    ) {}

    public function index(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $this->authorize('viewAny', [EndpointProfile::class, $tenant]);

        $profiles = EndpointProfile::query()
            ->with('secret')
            ->where('tenant_id', $tenant->id)
            ->where('environment_id', $environment->id)
            ->notArchived()
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => EndpointProfileResource::collection($profiles),
        ]);
    }

    public function store(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $actor = $this->resolvedActorUser($request);
        $this->authorize('create', [EndpointProfile::class, $tenant]);

        $validated = $this->validateProfile($request, $tenant->id, $environment->id);

        if (($validated['verify_tls'] ?? true) === false) {
            $this->authorize('disableTlsVerification', $tenant);
        }

        $profile = EndpointProfile::query()->create([
            ...$this->mapProfileAttributes($validated, $tenant->id, $environment->id, $actor->id),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $profile->load('secret');

        $this->auditLogger->logFromRequest(
            $request,
            action: 'endpoint_profile.created',
            resourceType: 'endpoint_profile',
            resourceId: $profile->public_id,
            tenantId: $tenant->id,
            summary: ['name' => $profile->name, 'base_url' => $profile->base_url],
        );

        return response()->json([
            'data' => new EndpointProfileResource($profile),
        ], 201);
    }

    public function show(Request $request, string $tenantId, string $environmentId, string $profileId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $profile = $this->findProfile($tenant->id, $environment->id, $profileId);
        $this->authorize('view', [$profile, $tenant]);

        return response()->json([
            'data' => new EndpointProfileResource($profile),
        ]);
    }

    public function update(Request $request, string $tenantId, string $environmentId, string $profileId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $actor = $this->resolvedActorUser($request);
        $profile = $this->findProfile($tenant->id, $environment->id, $profileId);
        $this->authorize('update', [$profile, $tenant]);

        $validated = $this->validateProfile($request, $tenant->id, $environment->id, $profile);

        if (array_key_exists('verify_tls', $validated) && $validated['verify_tls'] === false) {
            $this->authorize('disableTlsVerification', $tenant);
        }

        $profile->fill($this->mapProfileAttributes($validated, $tenant->id, $environment->id, $actor->id, $profile));
        $profile->updated_by = $actor->id;
        $profile->save();
        $profile->load('secret');

        $this->auditLogger->logFromRequest(
            $request,
            action: 'endpoint_profile.updated',
            resourceType: 'endpoint_profile',
            resourceId: $profile->public_id,
            tenantId: $tenant->id,
            summary: array_intersect_key($validated, array_flip(['name', 'base_url', 'verify_tls', 'enabled'])),
        );

        return response()->json([
            'data' => new EndpointProfileResource($profile->fresh(['secret'])),
        ]);
    }

    public function destroy(Request $request, string $tenantId, string $environmentId, string $profileId): Response
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $actor = $this->resolvedActorUser($request);
        $profile = $this->findProfile($tenant->id, $environment->id, $profileId);
        $this->authorize('delete', [$profile, $tenant]);

        $profile->updated_by = $actor->id;
        $profile->save();
        $profile->archive();

        $this->auditLogger->logFromRequest(
            $request,
            action: 'endpoint_profile.archived',
            resourceType: 'endpoint_profile',
            resourceId: $profile->public_id,
            tenantId: $tenant->id,
            summary: ['name' => $profile->name],
        );

        return response()->noContent();
    }

    public function test(Request $request, string $tenantId, string $environmentId, string $profileId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $actor = $this->resolvedActorUser($request);
        $profile = $this->findProfile($tenant->id, $environment->id, $profileId);
        $profile->load('secret');
        $this->authorize('test', [$profile, $tenant]);

        $validated = $request->validate([
            'path' => ['nullable', 'string', 'max:2048'],
            'body' => ['nullable', 'string', 'max:65535'],
        ]);

        $result = $this->profileTester->test($profile, $actor, $validated);

        $this->auditLogger->logFromRequest(
            $request,
            action: 'endpoint_profile.tested',
            resourceType: 'endpoint_profile',
            resourceId: $profile->public_id,
            tenantId: $tenant->id,
            summary: [
                'test_result_id' => $result->public_id,
                'response_status' => $result->response_status,
                'transport_error_code' => $result->transport_error_code,
            ],
        );

        return response()->json([
            'data' => new EndpointTestResultResource($result),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProfile(
        Request $request,
        int $tenantId,
        int $environmentId,
        ?EndpointProfile $existing = null,
    ): array {
        $authModes = array_map(static fn (AuthMode $mode) => $mode->value, AuthMode::cases());

        return $request->validate([
            'name' => array_filter([
                $existing ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('endpoint_profiles', 'name')
                    ->where('tenant_id', $tenantId)
                    ->where('environment_id', $environmentId)
                    ->whereNull('archived_at')
                    ->ignore($existing?->id),
            ]),
            'description' => ['nullable', 'string', 'max:65535'],
            'base_url' => array_filter([$existing ? 'sometimes' : 'required', 'string', 'url', 'max:2048']),
            'method' => ['sometimes', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['string', 'max:8192'],
            'auth_mode' => ['sometimes', 'string', Rule::in($authModes)],
            'auth_header_name' => ['nullable', 'string', 'max:255'],
            'auth_query_param' => ['nullable', 'string', 'max:255'],
            'secret_id' => [
                'nullable',
                'string',
                Rule::exists('secrets', 'public_id')
                    ->where('tenant_id', $tenantId)
                    ->where('environment_id', $environmentId)
                    ->whereNull('archived_at'),
            ],
            'connect_timeout' => ['sometimes', 'integer', 'min:1', 'max:300'],
            'total_timeout' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'follow_redirects' => ['sometimes', 'boolean'],
            'verify_tls' => ['sometimes', 'boolean'],
            'allowed_path_prefix' => ['nullable', 'string', 'max:2048'],
            'enabled' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mapProfileAttributes(
        array $validated,
        int $tenantId,
        int $environmentId,
        int $actorId,
        ?EndpointProfile $existing = null,
    ): array {
        $attributes = [];

        foreach (['name', 'description', 'base_url', 'method', 'connect_timeout', 'total_timeout', 'follow_redirects', 'verify_tls', 'allowed_path_prefix', 'enabled'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if (array_key_exists('auth_mode', $validated)) {
            $attributes['auth_mode'] = $validated['auth_mode'];
        }

        if (array_key_exists('secret_id', $validated)) {
            $attributes['secret_id'] = $validated['secret_id'] === null
                ? null
                : Secret::query()
                    ->where('public_id', $validated['secret_id'])
                    ->where('tenant_id', $tenantId)
                    ->where('environment_id', $environmentId)
                    ->value('id');
        }

        if (array_key_exists('headers', $validated) || array_key_exists('auth_header_name', $validated) || array_key_exists('auth_query_param', $validated)) {
            $headers = $validated['headers'] ?? ($existing?->visibleHeaders() ?? []);
            $authConfig = $existing?->authConfig() ?? [];

            if (! empty($validated['auth_header_name'])) {
                $authConfig['header_name'] = $validated['auth_header_name'];
            }

            if (! empty($validated['auth_query_param'])) {
                $authConfig['query_param'] = $validated['auth_query_param'];
            }

            if ($authConfig !== []) {
                $headers[EndpointProfile::AUTH_CONFIG_KEY] = $authConfig;
            }

            $attributes['headers_json'] = $headers;
        }

        $attributes['tenant_id'] = $tenantId;
        $attributes['environment_id'] = $environmentId;
        $attributes['updated_by'] = $actorId;

        return $attributes;
    }

    private function findProfile(int $tenantId, int $environmentId, string $profileId): EndpointProfile
    {
        return EndpointProfile::query()
            ->with('secret')
            ->where('public_id', $profileId)
            ->where('tenant_id', $tenantId)
            ->where('environment_id', $environmentId)
            ->notArchived()
            ->firstOrFail();
    }
}
