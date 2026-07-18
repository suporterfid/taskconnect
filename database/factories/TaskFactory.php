<?php

namespace Database\Factories;

use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Execution\RetryPolicy;
use App\Domain\Scheduling\ScheduleKind;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskSchedule;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'environment_id' => Environment::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'definition_status' => TaskDefinitionStatus::Draft,
            'method' => 'POST',
            'url_or_path' => 'https://receiver:8080/hook',
            'headers_json' => ['X-Test' => '1'],
            'query_json' => [],
            'body_template' => '{"ping":true}',
            'content_type' => 'application/json',
            'timezone' => 'UTC',
            'retry_policy_json' => RetryPolicy::default()->toArray(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['definition_status' => TaskDefinitionStatus::Active]);
    }

    public function withEveryMinuteSchedule(): static
    {
        return $this->afterCreating(function (Task $task): void {
            TaskSchedule::query()->create([
                'tenant_id' => $task->tenant_id,
                'task_id' => $task->id,
                'schedule_kind' => ScheduleKind::EveryNMinutes,
                'schedule_config_json' => [
                    'kind' => ScheduleKind::EveryNMinutes->value,
                    'timezone' => 'UTC',
                    'interval_minutes' => 15,
                ],
            ]);
        });
    }

    public function dueAt(string $utcTimestamp): static
    {
        return $this->state(fn () => [
            'definition_status' => TaskDefinitionStatus::Active,
            'next_run_at' => $utcTimestamp,
        ]);
    }
}
