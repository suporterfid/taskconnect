<?php

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\PipelineInstanceNode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PipelineInstanceNode */
class PipelineInstanceNodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'node_key' => $this->node_key,
            'task_type' => $this->task_type,
            'status' => $this->status->value,
            'depends_on' => $this->dependsOn(),
            'on_success' => $this->on_success,
            'on_failure' => $this->on_failure,
            'task_id' => $this->task?->public_id,
            'task_run_id' => $this->taskRun?->public_id,
            'run_state' => $this->taskRun?->run_state?->value,
        ];
    }
}
