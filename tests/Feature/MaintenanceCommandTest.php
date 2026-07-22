<?php

namespace Tests\Feature;

use App\Application\Retention\RetentionCleaner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MaintenanceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_command_runs_successfully(): void
    {
        $exit = Artisan::call('scheduler:maintenance');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Recovered', Artisan::output());
    }

    public function test_retention_cleaner_returns_counts(): void
    {
        $counts = app(RetentionCleaner::class)->run();

        $this->assertArrayHasKey('payload_snapshots_cleared', $counts);
        $this->assertArrayHasKey('attempt_metadata_cleared', $counts);
        $this->assertArrayHasKey('run_summaries_deleted', $counts);
        $this->assertArrayHasKey('audit_logs_deleted', $counts);
        $this->assertArrayHasKey('idempotency_keys_deleted', $counts);
        $this->assertArrayHasKey('dead_runs_deleted', $counts);
        $this->assertArrayHasKey('stale_claims_released', $counts);
    }
}
