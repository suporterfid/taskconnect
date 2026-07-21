<?php

namespace Tests\Feature\Phase1;

use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Execution\Enums\TriggerType;
use App\Domain\Scheduling\ScheduleKind;
use App\Domain\Shared\Enums\TenantRole;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskSchedule;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class TaskAuthorizationTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_read_only_viewer_cannot_pause_run_now_cancel_or_retry(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $viewer = User::factory()->create();
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $viewer->id,
            'role' => TenantRole::ReadOnlyViewer,
        ]);

        $task = $this->createActiveTask($tenant->id, $environment->id, $admin->id);
        $run = $this->createPendingRun($task);
        $deadRun = $this->createDeadRun($task);

        $base = $this->environmentRoute($tenant, $environment, '/tasks/'.$task->public_id);

        $this->actingAs($viewer)->postJson($base.'/pause')->assertForbidden();
        $this->actingAs($viewer)->postJson($base.'/run-now')->assertForbidden();
        $this->actingAs($viewer)->postJson(
            $this->environmentRoute($tenant, $environment, '/task-runs/'.$run->public_id.'/cancel')
        )->assertForbidden();
        $this->actingAs($viewer)->postJson(
            $this->environmentRoute($tenant, $environment, '/task-runs/'.$deadRun->public_id.'/retry')
        )->assertForbidden();
    }

    public function test_admin_can_pause_run_now_cancel_and_retry(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $task = $this->createActiveTask($tenant->id, $environment->id, $admin->id);
        $run = $this->createPendingRun($task);
        $deadRun = $this->createDeadRun($task);

        $base = $this->environmentRoute($tenant, $environment, '/tasks/'.$task->public_id);

        $this->actingAs($admin)->postJson($base.'/pause')->assertOk();
        $this->actingAs($admin)->postJson($base.'/run-now')->assertStatus(202);
        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/task-runs/'.$run->public_id.'/cancel')
        )->assertOk();
        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/task-runs/'.$deadRun->public_id.'/retry')
        )->assertStatus(202);
    }

    public function test_member_can_pause_and_run_now(): void
    {
        [$member, $tenant, $environment] = $this->createTenantAdmin('Member Tenant', TenantRole::TenantMember);
        $task = $this->createActiveTask($tenant->id, $environment->id, $member->id);

        $base = $this->environmentRoute($tenant, $environment, '/tasks/'.$task->public_id);

        $this->actingAs($member)->postJson($base.'/pause')->assertOk();
        $this->actingAs($member)->postJson($base.'/run-now')->assertStatus(202);
    }

    public function test_read_only_viewer_can_list_tasks(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $viewer = User::factory()->create();
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $viewer->id,
            'role' => TenantRole::ReadOnlyViewer,
        ]);

        $this->createActiveTask($tenant->id, $environment->id, $admin->id);

        $this->actingAs($viewer)->getJson(
            $this->environmentRoute($tenant, $environment, '/tasks')
        )->assertOk();
    }

    private function createActiveTask(int $tenantId, int $environmentId, int $userId): Task
    {
        $task = Task::factory()->create([
            'tenant_id' => $tenantId,
            'environment_id' => $environmentId,
            'definition_status' => TaskDefinitionStatus::Active,
            'next_run_at' => '2026-07-18T12:30:00Z',
            'url_or_path' => 'http://receiver:8080/hook',
            'created_by' => $userId,
        ]);

        TaskSchedule::query()->create([
            'tenant_id' => $tenantId,
            'task_id' => $task->id,
            'schedule_kind' => ScheduleKind::EveryNMinutes,
            'schedule_config_json' => [
                'kind' => ScheduleKind::EveryNMinutes->value,
                'timezone' => 'UTC',
                'interval_minutes' => 15,
            ],
        ]);

        return $task->fresh(['schedule']);
    }

    private function createPendingRun(Task $task): TaskRun
    {
        return TaskRun::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Manual,
            'occurrence_key' => 'occ-pending-'.uniqid(),
            'idempotency_key' => 'idem-pending-'.uniqid(),
            'run_state' => RunState::Pending,
            'attempt_count' => 1,
        ]);
    }

    private function createDeadRun(Task $task): TaskRun
    {
        return TaskRun::query()->create([
            'tenant_id' => $task->tenant_id,
            'environment_id' => $task->environment_id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Manual,
            'occurrence_key' => 'occ-dead-'.uniqid(),
            'idempotency_key' => 'idem-dead-'.uniqid(),
            'run_state' => RunState::Dead,
            'attempt_count' => 1,
            'finished_at' => now(),
        ]);
    }
}
