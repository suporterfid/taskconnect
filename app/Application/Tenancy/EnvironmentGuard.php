<?php

namespace App\Application\Tenancy;

use App\Infrastructure\Persistence\Eloquent\Environment;
use Illuminate\Validation\ValidationException;

final class EnvironmentGuard
{
    public function assertActive(Environment $environment): void
    {
        if ($environment->archived_at !== null) {
            throw ValidationException::withMessages([
                'environment' => ['This environment is archived and cannot accept changes.'],
            ]);
        }
    }
}
