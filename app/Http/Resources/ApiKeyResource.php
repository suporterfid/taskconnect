<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Infrastructure\Persistence\Eloquent\ApiKey */
class ApiKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'key_prefix' => $this->key_prefix,
            'permissions' => $this->permissions,
            'environment_id' => $this->environment?->public_id,
            'last_used_at' => $this->last_used_at?->utc()->toIso8601String(),
            'expires_at' => $this->expires_at?->utc()->toIso8601String(),
            'revoked_at' => $this->revoked_at?->utc()->toIso8601String(),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }
}
