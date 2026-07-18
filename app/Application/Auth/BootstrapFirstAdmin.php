<?php

namespace App\Application\Auth;

use App\Infrastructure\Persistence\Eloquent\User;
use App\Infrastructure\Persistence\Eloquent\UserPreference;
use Illuminate\Support\Facades\Hash;

class BootstrapFirstAdmin
{
    public function ensureExists(?string $email = null, ?string $password = null, ?string $name = null): ?User
    {
        if (User::query()->where('is_platform_admin', true)->exists()) {
            return null;
        }

        $email ??= env('BOOTSTRAP_ADMIN_EMAIL');
        $password ??= env('BOOTSTRAP_ADMIN_PASSWORD');
        $name ??= env('BOOTSTRAP_ADMIN_NAME', 'Platform Admin');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return null;
        }

        return $this->createAdmin($email, $password, $name);
    }

    public function createAdmin(string $email, string $password, string $name = 'Platform Admin'): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_platform_admin' => true,
        ]);

        UserPreference::query()->create([
            'user_id' => $user->id,
            'locale' => config('app.locale', 'en'),
            'timezone' => 'UTC',
        ]);

        return $user;
    }
}
