<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Infrastructure\Persistence\Eloquent\User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'email' => $this->email,
            'is_platform_admin' => $this->is_platform_admin,
            'preferences' => $this->whenLoaded('preferences', fn () => [
                'locale' => $this->preferences?->locale ?? 'en',
                'timezone' => $this->preferences?->timezone ?? 'UTC',
                'failure_emails_enabled' => $this->preferences?->failure_emails_enabled ?? true,
            ]),
            'created_at' => $this->created_at?->utc()->toIso8601String(),
        ];
    }
}
