<?php

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'action' => $this->action,
            'resource_type' => $this->resource_type,
            'resource_id' => $this->resource_id,
            'request_id' => $this->request_id,
            'summary' => $this->summary_json,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor === null ? null : [
                'id' => $this->actor->public_id,
                'name' => $this->actor->name,
                'email' => $this->actor->email,
            ]),
            'created_at' => $this->created_at?->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
