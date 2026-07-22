<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\SystemHeartbeat;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_sees_maintenance_and_retention(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true,
            'password' => Hash::make('secret'),
        ]);

        SystemHeartbeat::query()->create([
            'name' => 'scheduler.execute_due',
            'last_seen_at' => now(),
        ]);
        SystemHeartbeat::query()->create([
            'name' => 'scheduler.retry_due',
            'last_seen_at' => now(),
        ]);
        SystemHeartbeat::query()->create([
            'name' => 'scheduler.maintenance',
            'last_seen_at' => now()->subMinute(),
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/platform/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('scheduler_stale', false)
            ->assertJsonPath('retry_executor_stale', false)
            ->assertJsonStructure([
                'maintenance_last_seen_at',
                'retention' => [
                    'payload_snapshots_days',
                    'attempt_metadata_days',
                    'run_summary_days',
                    'audit_logs_days',
                ],
            ]);
    }

    public function test_stale_scheduler_marks_degraded(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true,
            'password' => Hash::make('secret'),
        ]);

        SystemHeartbeat::query()->create([
            'name' => 'scheduler.execute_due',
            'last_seen_at' => now()->subMinutes(10),
        ]);
        SystemHeartbeat::query()->create([
            'name' => 'scheduler.retry_due',
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/platform/health')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('scheduler_stale', true)
            ->assertJsonPath('retry_executor_stale', false);
    }

    public function test_authenticated_user_can_read_retention_defaults(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/platform/retention')
            ->assertOk()
            ->assertJsonPath('data.payload_snapshots_days', 30)
            ->assertJsonPath('data.audit_logs_days', 365);
    }
}
