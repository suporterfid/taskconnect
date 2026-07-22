<?php

namespace Tests\Feature\Scheduling;

use App\Application\Retention\RetentionCleaner;
use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Execution\Enums\TriggerType;
use App\Infrastructure\Persistence\Eloquent\AuditLog;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\CreatesScheduledTasks;
use Tests\TestCase;

class DlqCommandTest extends TestCase
{
    use CreatesScheduledTasks;
    use RefreshDatabase;

    public function test_list_show_and_replay_dead_run(): void
    {
        [$tenant, $environment, $user] = $this->createTenantContext();

        $task = Task::factory()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'created_by' => $user->id,
            'definition_status' => TaskDefinitionStatus::Active,
            'task_type' => 'document.convert',
            'priority' => 5,
            'weight' => 1,
            'url_or_path' => 'http://receiver:8080/hook',
        ]);

        $dead = TaskRun::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Scheduled,
            'occurrence_key' => 'occ-dead-1',
            'idempotency_key' => 'idem-dead-original',
            'run_state' => RunState::Dead,
            'attempt_count' => 3,
            'final_error_code' => '500',
            'final_http_status' => 500,
            'finished_at' => now()->utc(),
        ]);

        TaskRunAttempt::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_run_id' => $dead->id,
            'attempt_number' => 3,
            'attempt_state' => AttemptState::FailedTerminal,
            'response_status' => 500,
            'response_body_truncated' => '{"error":"boom"}',
            'transport_error_code' => null,
        ]);

        $listExit = Artisan::call('tasks:dlq:list', [
            '--workspace' => $environment->public_id,
            '--type' => 'document.convert',
        ]);
        $this->assertSame(0, $listExit);
        $this->assertStringContainsString($dead->public_id, Artisan::output());

        $showExit = Artisan::call('tasks:dlq:show', ['public_id' => $dead->public_id]);
        $this->assertSame(0, $showExit);
        $output = Artisan::output();
        $this->assertStringContainsString('final_error_code: 500', $output);
        $this->assertStringContainsString('{"error":"boom"}', $output);

        $replayExit = Artisan::call('tasks:dlq:replay', ['public_id' => $dead->public_id]);
        $this->assertSame(0, $replayExit);

        $dead->refresh();
        $this->assertSame(RunState::Dead, $dead->run_state);

        $newRun = TaskRun::query()
            ->where('task_id', $task->id)
            ->where('run_state', RunState::Pending)
            ->first();

        $this->assertNotNull($newRun);
        $this->assertSame(1, $newRun->attempt_count);
        $this->assertNotSame('idem-dead-original', $newRun->idempotency_key);
        $this->assertStringStartsWith('dlq:', $newRun->occurrence_key);

        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'dlq.replayed')
                ->where('resource_id', $dead->public_id)
                ->exists(),
        );
    }

    public function test_retention_prunes_old_dead_runs(): void
    {
        config(['retention.dead_runs_days' => 30]);

        [$tenant, $environment, $user] = $this->createTenantContext();
        $task = Task::factory()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'created_by' => $user->id,
        ]);

        $oldDead = TaskRun::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Manual,
            'occurrence_key' => 'occ-old-dead',
            'idempotency_key' => 'idem-old-dead',
            'run_state' => RunState::Dead,
            'attempt_count' => 1,
            'finished_at' => now()->subDays(45),
            'created_at' => now()->subDays(45),
        ]);

        $youngDead = TaskRun::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'task_id' => $task->id,
            'trigger_type' => TriggerType::Manual,
            'occurrence_key' => 'occ-young-dead',
            'idempotency_key' => 'idem-young-dead',
            'run_state' => RunState::Dead,
            'attempt_count' => 1,
            'finished_at' => now()->subDays(5),
            'created_at' => now()->subDays(5),
        ]);

        $counts = app(RetentionCleaner::class)->run();

        $this->assertSame(1, $counts['dead_runs_deleted']);
        $this->assertDatabaseMissing('task_runs', ['id' => $oldDead->id]);
        $this->assertDatabaseHas('task_runs', ['id' => $youngDead->id]);
    }
}
