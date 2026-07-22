<?php

namespace Tests\Feature\Scheduling;

use App\Application\Scheduling\SchedulerCycleRunner;
use App\Application\Scheduling\TickBudget;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesScheduledTasks;
use Tests\Support\FixedClock;
use Tests\TestCase;

class SchedulerBudgetTest extends TestCase
{
    use CreatesScheduledTasks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(Clock::class, FixedClock::at('2026-07-18T12:00:00Z'));
        config([
            'scheduler.claim_batch' => 20,
            'scheduler.claim_chunk' => 2,
            'task_types.global_inflight_ceiling' => 50,
            'task_types.types.default.concurrency_cap' => 50,
        ]);
    }

    public function test_execute_due_stops_claiming_when_budget_already_exhausted(): void
    {
        [$tenant, $environment, $user] = $this->createTenantContext();
        $dueAt = '2026-07-18T11:45:00Z';

        for ($i = 0; $i < 8; $i++) {
            $this->createActiveTaskDueAtIn($tenant, $environment, $user, $dueAt, [
                'name' => "due-$i",
                'priority' => 0,
                'weight' => 1,
            ]);
        }

        $now = 1_001.0;
        $budget = new TickBudget(1_000.0, 0.5, static function () use (&$now): float {
            return $now;
        });

        $stats = $this->app->make(SchedulerCycleRunner::class)->executeDue($budget);

        $this->assertTrue($stats['budget_stopped']);
        $this->assertSame(0, $stats['claimed']);
        $this->assertSame(0, TaskRun::query()->count());
    }

    public function test_chunked_claim_stops_mid_batch_when_budget_elapses(): void
    {
        [$tenant, $environment, $user] = $this->createTenantContext();
        $dueAt = '2026-07-18T11:45:00Z';

        for ($i = 0; $i < 10; $i++) {
            $this->createActiveTaskDueAtIn($tenant, $environment, $user, $dueAt, [
                'name' => "due-$i",
                'priority' => 0,
                'weight' => 1,
            ]);
        }

        $checkCount = 0;
        $budget = new TickBudget(1_000.0, 2.0, static function () use (&$checkCount): float {
            $checkCount++;
            // After the first claim+execute chunk, expire before the next claim.
            if ($checkCount >= 3) {
                return 1_010.0;
            }

            return 1_000.0;
        });

        $stats = $this->app->make(SchedulerCycleRunner::class)->executeDue($budget);

        $this->assertTrue($stats['budget_stopped']);
        $this->assertSame(2, $stats['claimed']);
        $this->assertSame(2, TaskRun::query()->count());
    }

    public function test_remaining_due_work_is_claimable_on_next_tick(): void
    {
        [$tenant, $environment, $user] = $this->createTenantContext();
        $dueAt = '2026-07-18T11:45:00Z';

        for ($i = 0; $i < 6; $i++) {
            $this->createActiveTaskDueAtIn($tenant, $environment, $user, $dueAt, [
                'name' => "due-$i",
                'priority' => 0,
                'weight' => 1,
            ]);
        }

        $checkCount = 0;
        $firstBudget = new TickBudget(1_000.0, 2.0, static function () use (&$checkCount): float {
            $checkCount++;
            if ($checkCount >= 3) {
                return 1_010.0;
            }

            return 1_000.0;
        });

        $first = $this->app->make(SchedulerCycleRunner::class)->executeDue($firstBudget);
        $this->assertTrue($first['budget_stopped']);
        $this->assertSame(2, $first['claimed']);

        $second = $this->app->make(SchedulerCycleRunner::class)->executeDue(
            new TickBudget(microtime(true), 60.0),
        );

        $this->assertFalse($second['budget_stopped']);
        $this->assertSame(4, $second['claimed']);
        $this->assertSame(6, TaskRun::query()->count());
    }
}
