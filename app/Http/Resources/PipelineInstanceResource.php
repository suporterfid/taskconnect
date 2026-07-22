<?php

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\PipelineInstance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PipelineInstance */
class PipelineInstanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'template_name' => $this->template_name,
            'status' => $this->status->value,
            /** Environment public id — v1 Extension workspace alias (R1). */
            'workspace_id' => $this->environment?->public_id,
            'nodes' => PipelineInstanceNodeResource::collection($this->whenLoaded('nodes')),
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
