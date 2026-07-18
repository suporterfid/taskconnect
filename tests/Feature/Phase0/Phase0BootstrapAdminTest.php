<?php

namespace Tests\Feature\Phase0;

use App\Application\Auth\BootstrapFirstAdmin;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase0BootstrapAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_admin_command_creates_platform_admin(): void
    {
        $this->artisan('platform:bootstrap-admin', [
            'email' => 'admin@taskconnect.local',
            'password' => 'bootstrap-secret',
            '--name' => 'Bootstrap Admin',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@taskconnect.local',
            'is_platform_admin' => true,
        ]);

        $user = User::query()->where('email', 'admin@taskconnect.local')->firstOrFail();
        $this->assertNotNull($user->public_id);
        $this->assertStringStartsWith('usr_', $user->public_id);
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'locale' => 'en',
            'timezone' => 'UTC',
        ]);
    }

    public function test_bootstrap_admin_command_fails_when_admin_already_exists(): void
    {
        User::factory()->platformAdmin()->create();

        $this->artisan('platform:bootstrap-admin', [
            'email' => 'another@taskconnect.local',
            'password' => 'bootstrap-secret',
        ])->assertFailed();
    }

    public function test_bootstrap_first_admin_service_uses_env_values(): void
    {
        config()->set('app.env', 'local');

        putenv('BOOTSTRAP_ADMIN_EMAIL=env-admin@taskconnect.local');
        putenv('BOOTSTRAP_ADMIN_PASSWORD=env-secret');

        $user = app(BootstrapFirstAdmin::class)->ensureExists();

        $this->assertNotNull($user);
        $this->assertSame('env-admin@taskconnect.local', $user->email);
        $this->assertTrue($user->isPlatformAdmin());

        putenv('BOOTSTRAP_ADMIN_EMAIL');
        putenv('BOOTSTRAP_ADMIN_PASSWORD');
    }
}
