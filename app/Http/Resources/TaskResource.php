<?php

namespace App\Http\Resources;

use App\Domain\Scheduling\ScheduleDescription;
use App\Domain\Scheduling\ScheduleKind;
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
        $runAt = null;

        if ($schedule !== null) {
            $scheduleHuman = ScheduleDescription::fromConfig($schedule->toScheduleConfig());
            $scheduleHuman = [
                'kind' => $scheduleHuman->kind->value,
                'parts' => $scheduleHuman->parts,
            ];

            if ($schedule->schedule_kind === ScheduleKind::Once) {
                $config = is_array($schedule->schedule_config_json) ? $schedule->schedule_config_json : [];
                $runAt = isset($config['at']) && is_string($config['at']) ? $config['at'] : null;
            }
        }

        $data = [
            'id' => $this->public_id,
            /** Environment public id — v1 Extension workspace alias (R1). */
            'workspace_id' => $this->environment?->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'definition_status' => $this->definition_status->value,
            'task_type' => $this->task_type,
            'priority' => $this->priority,
            'weight' => $this->weight,
            'timeout_ms' => $this->timeout_ms,
            'egress_profile' => $this->egress_profile,
            'coalesce_key' => $this->coalesce_key,
            'method' => $this->method,
            'url_or_path' => $this->url_or_path,
            'endpoint_profile_id' => $this->endpointProfile?->public_id,
            'headers' => $this->headers_json ?? [],
            'query' => $this->query_json ?? [],
            'body' => $this->body_template,
            'content_type' => $this->content_type,
            'timezone' => $this->timezone,
            'retry_policy' => $this->retry_policy_json,
            /** Delayed one-shot instant when schedule kind is once (R16); null for recurring kinds. */
            'run_at' => $runAt,
            'next_run_at' => $this->next_run_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'last_run_at' => $this->last_run_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'last_run_state' => $this->last_run_state,
            'schedule' => $schedule?->schedule_config_json,
            'schedule_human' => $scheduleHuman,
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];

        if ($this->wantsSpecV1Aliases($request)) {
            $data['target_url'] = $this->url_or_path;
            $data['payload'] = $this->specV1Payload();
        }

        return $data;
    }

    /**
     * Opt-in §6.1 sketch-literal field names (v1 Extension spec residual, issue #78).
     * Never enabled by default — v0 field names above remain canonical.
     */
    private function wantsSpecV1Aliases(Request $request): bool
    {
        return $request->query('aliases') === 'spec-v1';
    }

    /**
     * `payload` mirrors `body`: JSON-decoded when `content_type` is a JSON type and the
     * body parses, otherwise the raw string. Null body stays null.
     */
    private function specV1Payload(): mixed
    {
        $body = $this->body_template;

        if ($body === null) {
            return null;
        }

        if ($this->content_type !== null && str_contains($this->content_type, 'json')) {
            $decoded = json_decode($body, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $body;
    }
}
