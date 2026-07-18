<?php

namespace App\Application\Tasks;

use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Scheduling\ScheduleCalculator;
use App\Domain\Scheduling\ScheduleConfig;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskSchedule;

final class TaskScheduleService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly ScheduleCalculator $scheduleCalculator,
    ) {
    }

    public function syncNextRunAt(Task $task): void
    {
        if ($task->definition_status !== TaskDefinitionStatus::Active) {
            return;
        }

        $schedule = $task->schedule;

        if ($schedule === null) {
            $task->next_run_at = null;
            $task->save();

            return;
        }

        $next = $this->scheduleCalculator->nextRunAt($schedule->toScheduleConfig());
        $task->next_run_at = $next;
        $schedule->last_calculated_at = $this->clock->nowUtc();
        $schedule->save();
        $task->save();
    }

    public function upsertSchedule(Task $task, ScheduleConfig $config): TaskSchedule
    {
        $payload = [
            'tenant_id' => $task->tenant_id,
            'schedule_kind' => $config->kind,
            'schedule_config_json' => $this->configToArray($config),
            'starts_at' => $config->startsAt,
            'ends_at' => $config->endsAt,
        ];

        $schedule = $task->schedule;

        if ($schedule === null) {
            $schedule = $task->schedule()->create($payload);
        } else {
            $schedule->fill($payload);
            $schedule->save();
        }

        return $schedule;
    }

    /**
     * @return array<string, mixed>
     */
    private function configToArray(ScheduleConfig $config): array
    {
        $data = [
            'kind' => $config->kind->value,
            'timezone' => $config->timezone,
        ];

        if ($config->at !== null) {
            $data['at'] = $config->at->format('Y-m-d\TH:i:s\Z');
        }

        if ($config->intervalMinutes !== null) {
            $data['interval_minutes'] = $config->intervalMinutes;
        }

        if ($config->minute !== null) {
            $data['minute'] = $config->minute;
        }

        if ($config->time !== null) {
            $data['time'] = $config->time;
        }

        if ($config->weekdays !== null) {
            $data['weekdays'] = $config->weekdays;
        }

        if ($config->dayOfMonth !== null) {
            $data['day'] = $config->dayOfMonth;
        }

        if ($config->startsAt !== null) {
            $data['starts_at'] = $config->startsAt->format('Y-m-d\TH:i:s\Z');
        }

        if ($config->endsAt !== null) {
            $data['ends_at'] = $config->endsAt->format('Y-m-d\TH:i:s\Z');
        }

        return $data;
    }
}
