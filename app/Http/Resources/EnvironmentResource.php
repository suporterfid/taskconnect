<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Infrastructure\Persistence\Eloquent\Environment */
class EnvironmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            /** Same as id — Environment is the v1 Extension workspace (R1). */
            'workspace_id' => $this->public_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'notifications' => [
                'dead_run_email_enabled' => (bool) ($this->dead_run_email_enabled ?? true),
                'dead_run_webhook_enabled' => (bool) ($this->dead_run_webhook_enabled ?? false),
                'dead_run_webhook_url' => $this->dead_run_webhook_url,
            ],
            'submit_rate_limit_per_minute' => $this->submit_rate_limit_per_minute,
            'archived_at' => $this->archived_at?->utc()->toIso8601String(),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }
}
