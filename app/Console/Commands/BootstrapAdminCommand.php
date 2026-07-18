<?php

namespace App\Console\Commands;

use App\Application\Auth\BootstrapFirstAdmin;
use Illuminate\Console\Command;

class BootstrapAdminCommand extends Command
{
    protected $signature = 'platform:bootstrap-admin {email} {password} {--name=Platform Admin}';

    protected $description = 'Create the first platform administrator';

    public function handle(BootstrapFirstAdmin $bootstrapFirstAdmin): int
    {
        if (\App\Infrastructure\Persistence\Eloquent\User::query()->where('is_platform_admin', true)->exists()) {
            $this->error('A platform administrator already exists.');

            return self::FAILURE;
        }

        $user = $bootstrapFirstAdmin->createAdmin(
            email: (string) $this->argument('email'),
            password: (string) $this->argument('password'),
            name: (string) $this->option('name'),
        );

        $this->info('Platform administrator created: '.$user->email);

        return self::SUCCESS;
    }
}
