<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Audit\AuditLogger;
use App\Domain\Execution\Outbound\EgressProfile;
use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyViolation;
use App\Http\Controllers\Controller;
use App\Http\Resources\EnvironmentResource;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EnvironmentController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OutboundPolicy $outboundPolicy,
    ) {}

    public function index(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $this->authorize('viewAny', [Environment::class, $tenant]);

        $environments = $tenant->environments()->orderBy('name')->get();

        return response()->json([
            'data' => EnvironmentResource::collection($environments),
        ]);
    }

    public function store(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $this->authorize('create', [Environment::class, $tenant]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('environments', 'slug')->where('tenant_id', $tenant->id),
            ],
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        $environment = $tenant->environments()->create([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        $this->auditLogger->logFromRequest(
            $request,
            action: 'environment.created',
            resourceType: 'environment',
            resourceId: $environment->public_id,
            tenantId: $tenant->id,
            environmentId: $environment->id,
            summary: ['name' => $environment->name, 'slug' => $environment->slug],
        );

        return response()->json([
            'data' => new EnvironmentResource($environment),
        ], 201);
    }

    public function update(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $this->authorize('update', [$environment, $tenant]);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:environments,slug,'.$environment->id.',id,tenant_id,'.$tenant->id],
            'notifications' => ['sometimes', 'array'],
            'notifications.dead_run_email_enabled' => ['sometimes', 'required', 'boolean'],
            'notifications.dead_run_webhook_enabled' => ['sometimes', 'required', 'boolean'],
            'notifications.dead_run_webhook_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'dead_run_email_enabled' => ['sometimes', 'required', 'boolean'],
            'dead_run_webhook_enabled' => ['sometimes', 'required', 'boolean'],
            'dead_run_webhook_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        $attributes = [];
        if (isset($validated['name'])) {
            $attributes['name'] = $validated['name'];
        }
        if (isset($validated['slug'])) {
            $attributes['slug'] = $validated['slug'];
        }

        $notifications = is_array($validated['notifications'] ?? null) ? $validated['notifications'] : [];
        foreach (['dead_run_email_enabled', 'dead_run_webhook_enabled', 'dead_run_webhook_url'] as $field) {
            if (array_key_exists($field, $notifications)) {
                $attributes[$field] = $notifications[$field];
            } elseif (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if (array_key_exists('dead_run_webhook_url', $attributes) && is_string($attributes['dead_run_webhook_url']) && trim($attributes['dead_run_webhook_url']) !== '') {
            $this->assertWebhookUrlAllowed(trim($attributes['dead_run_webhook_url']));
            $attributes['dead_run_webhook_url'] = trim($attributes['dead_run_webhook_url']);
        }

        $environment->fill($attributes);
        $environment->save();

        $this->auditLogger->logFromRequest(
            $request,
            action: 'environment.updated',
            resourceType: 'environment',
            resourceId: $environment->public_id,
            tenantId: $tenant->id,
            environmentId: $environment->id,
            summary: $this->auditSummary($attributes),
        );

        return response()->json([
            'data' => new EnvironmentResource($environment->fresh()),
        ]);
    }

    public function destroy(Request $request, string $tenantId, string $environmentId): Response
    {
        $tenant = $this->resolvedTenant($request);
        $environment = $this->resolvedEnvironment($request);
        $this->authorize('delete', [$environment, $tenant]);

        $environment->archive();

        $this->auditLogger->logFromRequest(
            $request,
            action: 'environment.archived',
            resourceType: 'environment',
            resourceId: $environment->public_id,
            tenantId: $tenant->id,
        );

        return response()->noContent();
    }

    private function assertWebhookUrlAllowed(string $url): void
    {
        try {
            $this->outboundPolicy->validateUrl($url, [], EgressProfile::PublicCrawl);
        } catch (OutboundPolicyViolation $exception) {
            throw ValidationException::withMessages([
                'notifications.dead_run_webhook_url' => [$exception->getMessage() !== '' ? $exception->getMessage() : 'Webhook URL is not allowed.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function auditSummary(array $attributes): array
    {
        $summary = $attributes;
        if (isset($summary['dead_run_webhook_url']) && is_string($summary['dead_run_webhook_url'])) {
            $host = parse_url($summary['dead_run_webhook_url'], PHP_URL_HOST);
            $summary['dead_run_webhook_host'] = is_string($host) ? $host : null;
            unset($summary['dead_run_webhook_url']);
        }

        return $summary;
    }

    private function resolvedTenant(Request $request): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return $tenant;
    }

    private function resolvedEnvironment(Request $request): Environment
    {
        /** @var Environment $environment */
        $environment = $request->attributes->get('environment');

        return $environment;
    }
}
