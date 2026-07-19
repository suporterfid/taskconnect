<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Infrastructure\Persistence\Eloquent\TenantMembership */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'role' => $this->role?->value ?? $this->role,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
