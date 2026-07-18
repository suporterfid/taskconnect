<?php

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskRun */
class TaskRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'task_id' => $this->task?->public_id,
            'trigger_type' => $this->trigger_type->value,
            'scheduled_for' => $this->scheduled_for?->utc()->format('Y-m-d\TH:i:s\Z'),
            'idempotency_key' => $this->idempotency_key,
            'run_state' => $this->run_state->value,
            'attempt_count' => $this->attempt_count,
            'next_attempt_at' => $this->next_attempt_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'started_at' => $this->started_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'finished_at' => $this->finished_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'final_http_status' => $this->final_http_status,
            'final_error_code' => $this->final_error_code,
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
