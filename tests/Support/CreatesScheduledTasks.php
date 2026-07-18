<?php

namespace Tests\Support;

use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Scheduling\ScheduleKind;
use App\Domain\Shared\Enums\TenantRole;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskSchedule;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;

trait CreatesScheduledTasks
{
    protected function createActiveTaskDueAt(string $utcTimestamp, int $intervalMinutes = 15): Task
    {
        [$tenant, $environment, $user] = $this->createTenantContext();

        $task = Task::factory()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'definition_status' => TaskDefinitionStatus::Active,
            'next_run_at' => $utcTimestamp,
            'url_or_path' => 'http://receiver:8080/hook',
            'created_by' => $user->id,
        ]);

        TaskSchedule::query()->create([
            'tenant_id' => $tenant->id,
            'task_id' => $task->id,
            'schedule_kind' => ScheduleKind::EveryNMinutes,
            'schedule_config_json' => [
                'kind' => ScheduleKind::EveryNMinutes->value,
                'timezone' => 'UTC',
                'interval_minutes' => $intervalMinutes,
            ],
        ]);

        return $task->fresh(['schedule']);
    }

    /**
     * @return array{0: Tenant, 1: Environment, 2: User}
     */
    protected function createTenantContext(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $environment = Environment::factory()->create(['tenant_id' => $tenant->id]);

        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => TenantRole::TenantAdmin,
        ]);

        return [$tenant, $environment, $user];
    }
}
