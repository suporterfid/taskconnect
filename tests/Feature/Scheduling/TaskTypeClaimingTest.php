<?php

namespace Tests\Feature\Scheduling;

use App\Application\Scheduling\DueTaskClaimer;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesScheduledTasks;
use Tests\Support\FixedClock;
use Tests\TestCase;

class TaskTypeClaimingTest extends TestCase
{
    use CreatesScheduledTasks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(Clock::class, FixedClock::at('2026-07-18T12:00:00Z'));
    }

    public function test_claimer_respects_convert_concurrency_cap(): void
    {
        config([
            'task_types.global_inflight_ceiling' => 10,
            'task_types.types.document.convert.concurrency_cap' => 2,
            'task_types.types.document.convert.weight' => 1,
            'task_types.types.document.convert.priority' => 5,
        ]);

        [$tenant, $environment, $user] = $this->createTenantContext();
        $dueAt = '2026-07-18T11:45:00Z';

        for ($i = 0; $i < 5; $i++) {
            $this->createActiveTaskDueAtIn($tenant, $environment, $user, $dueAt, [
                'name' => "convert-$i",
                'task_type' => 'document.convert',
                'priority' => 5,
                'weight' => 1,
            ]);
        }

        $claimed = $this->app->make(DueTaskClaimer::class)->claim(10);

        $this->assertCount(2, $claimed);
        $this->assertSame(2, TaskRun::query()->count());

        // Second tick still blocked by Pending/Running in-flight accounting.
        $second = $this->app->make(DueTaskClaimer::class)->claim(10);
        $this->assertCount(0, $second);
    }

    public function test_claimer_respects_global_inflight_ceiling(): void
    {
        config([
            'task_types.global_inflight_ceiling' => 4,
            'task_types.types.document.convert.concurrency_cap' => 10,
            'task_types.types.kb.index.concurrency_cap' => 10,
            'task_types.types.document.convert.weight' => 1,
            'task_types.types.kb.index.weight' => 1,
        ]);

        [$tenant, $environment, $user] = $this->createTenantContext();
        $dueAt = '2026-07-18T11:45:00Z';

        for ($i = 0; $i < 3; $i++) {
            $this->createActiveTaskDueAtIn($tenant, $environment, $user, $dueAt, [
                'name' => "convert-$i",
                'task_type' => 'document.convert',
                'priority' => 5,
                'weight' => 1,
            ]);
            $this->createActiveTaskDueAtIn($tenant, $environment, $user, $dueAt, [
                'name' => "index-$i",
                'task_type' => 'kb.index',
                'priority' => 5,
                'weight' => 1,
            ]);
        }

        $claimed = $this->app->make(DueTaskClaimer::class)->claim(20);

        $this->assertCount(4, $claimed);
        $this->assertSame(4, TaskRun::query()->count());
    }

    public function test_reminder_is_claimed_when_convert_cap_is_saturated(): void
    {
        config([
            'task_types.global_inflight_ceiling' => 4,
            'task_types.types.document.convert.concurrency_cap' => 2,
            'task_types.types.document.convert.priority' => 5,
            'task_types.types.document.convert.weight' => 1,
            'task_types.types.note.reminder.concurrency_cap' => 4,
            'task_types.types.note.reminder.priority' => 8,
            'task_types.types.note.reminder.weight' => 1,
        ]);

        [$tenant, $environment, $user] = $this->createTenantContext();
        $dueAt = '2026-07-18T11:45:00Z';

        for ($i = 0; $i < 5; $i++) {
            $this->createActiveTaskDueAtIn($tenant, $environment, $user, $dueAt, [
                'name' => "convert-$i",
                'task_type' => 'document.convert',
                'priority' => 5,
                'weight' => 1,
            ]);
        }

        $reminder = $this->createActiveTaskDueAtIn($tenant, $environment, $user, $dueAt, [
            'name' => 'reminder-1',
            'task_type' => 'note.reminder',
            'priority' => 8,
            'weight' => 1,
        ]);

        $claimed = $this->app->make(DueTaskClaimer::class)->claim(10);

        $claimedTaskIds = array_map(
            static fn ($claimedAttempt) => $claimedAttempt->run->task_id,
            $claimed,
        );

        // Higher-priority reminder first, then convert up to cap=2 (global still has room).
        $this->assertContains($reminder->id, $claimedTaskIds);
        $this->assertLessThanOrEqual(3, count($claimed));
        $convertClaimed = TaskRun::query()
            ->whereIn('task_id', function ($query) {
                $query->select('id')->from('tasks')->where('task_type', 'document.convert');
            })
            ->count();
        $this->assertSame(2, $convertClaimed);
        $this->assertSame(1, TaskRun::query()->where('task_id', $reminder->id)->count());
    }
}
