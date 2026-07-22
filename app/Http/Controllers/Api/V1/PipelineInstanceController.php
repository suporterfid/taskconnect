<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Audit\AuditLogger;
use App\Application\Pipelines\PipelineInstanceService;
use App\Application\Tenancy\EnvironmentGuard;
use App\Domain\Pipelines\InvalidPipelineTemplateException;
use App\Domain\Pipelines\PipelineTemplateCatalog;
use App\Http\Controllers\Controller;
use App\Http\Resources\PipelineInstanceResource;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\PipelineInstance;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineInstanceController extends Controller
{
    public function __construct(
        private readonly PipelineInstanceService $instances,
        private readonly PipelineTemplateCatalog $templates,
        private readonly AuditLogger $auditLogger,
        private readonly EnvironmentGuard $environmentGuard,
    ) {
    }

    public function templates(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->authorize('viewAny', [PipelineInstance::class, $tenant]);

        $items = [];
        foreach ($this->templates->knownNames() as $name) {
            $template = $this->templates->get($name);
            $nodes = [];
            foreach ($template['nodes'] as $key => $node) {
                $nodes[] = [
                    'node_key' => $key,
                    'task_type' => $node['task_type'],
                    'depends_on' => $node['depends_on'],
                    'on_success' => $node['on_success'],
                    'on_failure' => $node['on_failure'],
                ];
            }
            $items[] = [
                'name' => $template['name'],
                'description' => $template['description'],
                'nodes' => $nodes,
            ];
        }

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, string $tenantId, string $environmentId, string $templateName): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $this->authorize('create', [PipelineInstance::class, $tenant]);
        $this->environmentGuard->assertActive($environment);

        $validated = $request->validate([
            'nodes' => ['required', 'array', 'min:1'],
            'nodes.*' => ['required', 'array'],
            'nodes.*.method' => ['sometimes', 'string', 'max:16'],
            'nodes.*.url_or_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'nodes.*.url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'nodes.*.path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'nodes.*.headers' => ['sometimes', 'nullable', 'array'],
            'nodes.*.query' => ['sometimes', 'nullable', 'array'],
            'nodes.*.body' => ['sometimes', 'nullable'],
            'nodes.*.body_template' => ['sometimes', 'nullable', 'string'],
            'nodes.*.content_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nodes.*.egress_profile' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        try {
            $instance = $this->instances->create(
                $tenant,
                $environment,
                $templateName,
                $validated['nodes'],
                $this->actorUserId($request),
            );
        } catch (InvalidPipelineTemplateException $exception) {
            return response()->json([
                'error' => [
                    'code' => 'pipeline_template_invalid',
                    'message' => $exception->getMessage(),
                ],
            ], 422);
        }

        $this->auditLogger->logFromRequest(
            $request,
            action: 'pipeline.instance_created',
            resourceType: 'pipeline_instance',
            resourceId: $instance->public_id,
            tenantId: $tenant->id,
            summary: ['template_name' => $templateName],
        );

        return response()->json(['data' => new PipelineInstanceResource($instance)], 201);
    }

    public function show(Request $request, string $tenantId, string $environmentId, string $templateName, string $instanceId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $instance = $this->resolveInstance($tenant, $environment, $templateName, $instanceId);
        $this->authorize('view', [$instance, $tenant]);

        return response()->json(['data' => new PipelineInstanceResource($instance)]);
    }

    private function resolveInstance(
        Tenant $tenant,
        Environment $environment,
        string $templateName,
        string $instanceId,
    ): PipelineInstance {
        /** @var PipelineInstance $instance */
        $instance = PipelineInstance::query()
            ->where('tenant_id', $tenant->id)
            ->where('environment_id', $environment->id)
            ->where('template_name', $templateName)
            ->where('public_id', $instanceId)
            ->with(['nodes.task', 'nodes.taskRun', 'environment'])
            ->firstOrFail();

        return $instance;
    }

    private function tenant(Request $request): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return $tenant;
    }

    private function environment(Request $request): Environment
    {
        /** @var Environment $environment */
        $environment = $request->attributes->get('environment');

        return $environment;
    }

    private function actorUserId(Request $request): ?int
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $id = $user->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
