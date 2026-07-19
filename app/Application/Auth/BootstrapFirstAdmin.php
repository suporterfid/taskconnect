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

        $email ??= self::readEnv('BOOTSTRAP_ADMIN_EMAIL');
        $password ??= self::readEnv('BOOTSTRAP_ADMIN_PASSWORD');
        $name ??= self::readEnv('BOOTSTRAP_ADMIN_NAME') ?? 'Platform Admin';

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

    /**
     * Read a bootstrap configuration value from the environment.
     *
     * Laravel's cached env repository does not observe values set via
     * putenv() after boot, so we fall back to getenv() to honour variables
     * exported into the process environment at deploy time.
     */
    private static function readEnv(string $key): ?string
    {
        $value = env($key);

        if (! is_string($value) || $value === '') {
            $fromProcess = getenv($key);
            $value = $fromProcess === false ? null : $fromProcess;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
