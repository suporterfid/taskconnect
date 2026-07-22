<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Infrastructure\Persistence\Eloquent\EndpointProfile */
class EndpointProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            /** Environment public id — v1 Extension workspace alias (R1). */
            'workspace_id' => $this->environment?->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'base_url' => $this->base_url,
            'method' => $this->method,
            'headers' => $this->visibleHeaders(),
            'auth_mode' => $this->auth_mode?->value ?? $this->auth_mode,
            'auth_header_name' => $this->authConfig()['header_name'] ?? null,
            'auth_query_param' => $this->authConfig()['query_param'] ?? null,
            'secret_id' => $this->secret?->public_id,
            'connect_timeout' => $this->connect_timeout,
            'total_timeout' => $this->total_timeout,
            'follow_redirects' => $this->follow_redirects,
            'verify_tls' => $this->verify_tls,
            'allowed_path_prefix' => $this->allowed_path_prefix,
            'enabled' => $this->enabled,
            'archived_at' => $this->archived_at?->utc()->toIso8601String(),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }
}
