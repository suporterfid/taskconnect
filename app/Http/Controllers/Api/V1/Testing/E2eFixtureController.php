<?php

namespace App\Http\Controllers\Api\V1\Testing;

use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Execution\Enums\TriggerType;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Test-only fixture seeding for Playwright E2E (issue #79). Gated to local/testing by
 * the `e2e.testing.only` middleware — never reachable on a real deploy. Bypasses the
 * scheduler entirely to deterministically create a dead run for DLQ inspect/replay
 * assertions, since there's no HTTP-only way to drive real cron ticks from Playwright.
 */
class E2eFixtureController extends Controller
{
    public function seedDeadRun(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $environment = $this->environment($request);
        $this->authorize('create', [Task::class, $tenant]);

        $suffix = (string) Str::uuid();

        $task = Task::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'created_by' => $this->actorUserId($request),
            'name' => 'E2E DLQ fixture',
            'definition_status' => TaskDefinitionStatus::Active,
            'task_type' => 'document.convert',
            'priority' => 5,
            'weight' => 1,
            'method' => 'POST',
            'url_or_path' => 'http://e2e-fixture.invalid/hook',
        ]);

        $run = TaskRun::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Scheduled,
            'occurrence_key' => 'e2e-dlq-'.$suffix,
            'idempotency_key' => 'e2e-dlq-idem-'.$suffix,
            'run_state' => RunState::Dead,
            'attempt_count' => 1,
            'final_error_code' => '500',
            'final_http_status' => 500,
            'finished_at' => now()->utc(),
        ]);

        TaskRunAttempt::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_run_id' => $run->id,
            'attempt_number' => 1,
            'attempt_state' => AttemptState::FailedTerminal,
            'response_status' => 500,
            'response_body_truncated' => '{"error":"e2e fixture"}',
        ]);

        return response()->json([
            'data' => [
                'task_id' => $task->public_id,
                'run_id' => $run->public_id,
            ],
        ], 201);
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
