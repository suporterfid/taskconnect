<?php

namespace App\Http\Resources;

use App\Domain\Scheduling\ScheduleDescription;
use App\Infrastructure\Persistence\Eloquent\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $schedule = $this->schedule;
        $scheduleHuman = null;

        if ($schedule !== null) {
            $scheduleHuman = ScheduleDescription::fromConfig($schedule->toScheduleConfig());
            $scheduleHuman = [
                'kind' => $scheduleHuman->kind->value,
                'parts' => $scheduleHuman->parts,
            ];
        }

        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'definition_status' => $this->definition_status->value,
            'method' => $this->method,
            'url_or_path' => $this->url_or_path,
            'endpoint_profile_id' => $this->endpointProfile?->public_id,
            'headers' => $this->headers_json ?? [],
            'query' => $this->query_json ?? [],
            'body' => $this->body_template,
            'content_type' => $this->content_type,
            'timezone' => $this->timezone,
            'retry_policy' => $this->retry_policy_json,
            'next_run_at' => $this->next_run_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'last_run_at' => $this->last_run_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'last_run_state' => $this->last_run_state,
            'schedule' => $schedule?->schedule_config_json,
            'schedule_human' => $scheduleHuman,
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
