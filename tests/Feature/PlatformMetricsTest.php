<?php

namespace Tests\Feature;

use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Execution\Enums\TriggerType;
use App\Infrastructure\Persistence\Eloquent\SystemHeartbeat;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesScheduledTasks;
use Tests\TestCase;

class PlatformMetricsTest extends TestCase
{
    use CreatesScheduledTasks;
    use RefreshDatabase;

    public function test_platform_admin_receives_prometheus_text(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true,
            'password' => Hash::make('secret'),
        ]);

        [$tenant, $environment, $user] = $this->createTenantContext();

        $task = Task::factory()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'definition_status' => TaskDefinitionStatus::Active,
            'task_type' => 'note.reminder',
            'created_by' => $user->id,
            'url_or_path' => 'https://example.com/hook',
        ]);

        $pending = TaskRun::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Scheduled,
            'occurrence_key' => 'occ-1',
            'idempotency_key' => 'idem-1',
            'run_state' => RunState::Pending,
            'attempt_count' => 0,
        ]);

        TaskRun::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Scheduled,
            'occurrence_key' => 'occ-2',
            'idempotency_key' => 'idem-2',
            'run_state' => RunState::Dead,
            'attempt_count' => 3,
        ]);

        TaskRun::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Scheduled,
            'occurrence_key' => 'occ-3',
            'idempotency_key' => 'idem-3',
            'run_state' => RunState::Running,
            'attempt_count' => 1,
        ]);

        TaskRunAttempt::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_run_id' => $pending->id,
            'attempt_number' => 1,
            'attempt_state' => AttemptState::Succeeded,
            'duration_ms' => 150,
        ]);

        SystemHeartbeat::query()->create([
            'name' => 'scheduler.execute_due',
            'last_seen_at' => now(),
            'meta_json' => [
                'duration_ms' => 1200,
                'budget_seconds' => 45,
                'budget_stopped' => false,
            ],
        ]);
        SystemHeartbeat::query()->create([
            'name' => 'scheduler.retry_due',
            'last_seen_at' => now(),
            'meta_json' => [
                'duration_ms' => 300,
                'budget_seconds' => 45,
                'budget_stopped' => true,
            ],
        ]);

        $response = $this->actingAs($admin)->get('/api/v1/platform/metrics');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));

        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('taskconnect_queue_depth{state="pending"} 1', $body);
        $this->assertStringContainsString('taskconnect_dlq_size 1', $body);
        $this->assertStringContainsString('taskconnect_inflight 1', $body);
        $this->assertStringContainsString('taskconnect_inflight_by_type{task_type="note.reminder"} 1', $body);
        $this->assertStringContainsString('taskconnect_attempt_duration_ms{task_type="note.reminder",quantile="0.5"} 150', $body);
        $this->assertStringContainsString('taskconnect_scheduler_tick_duration_seconds{command="execute_due"} 1.2', $body);
        $this->assertStringContainsString('taskconnect_scheduler_budget_stopped{command="retry_due"} 1', $body);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/api/v1/platform/metrics')
            ->assertForbidden();
    }
}
