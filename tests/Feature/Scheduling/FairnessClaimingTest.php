<?php

namespace Tests\Feature\Scheduling;

use App\Application\Scheduling\DueTaskClaimer;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesScheduledTasks;
use Tests\Support\FixedClock;
use Tests\TestCase;

class FairnessClaimingTest extends TestCase
{
    use CreatesScheduledTasks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(Clock::class, FixedClock::at('2026-07-18T12:00:00Z'));
    }

    public function test_saturated_workspace_a_does_not_starve_workspace_b(): void
    {
        config([
            'task_types.global_inflight_ceiling' => 4,
            'task_types.types.note.reminder.concurrency_cap' => 10,
            'task_types.types.note.reminder.weight' => 1,
            'task_types.types.note.reminder.priority' => 5,
            'scheduler.fairness_workspace_weight' => 1,
            'scheduler.fairness_mode' => 'rr',
            'scheduler.priority_preemption_min' => null,
        ]);

        [$tenantA, $envA, $userA] = $this->createTenantContext();
        $envB = Environment::factory()->create(['tenant_id' => $tenantA->id, 'slug' => 'other-ws']);

        $dueAt = '2026-07-18T11:45:00Z';

        // Workspace A floods with due light work.
        for ($i = 0; $i < 10; $i++) {
            $this->createActiveTaskDueAtIn($tenantA, $envA, $userA, $dueAt, [
                'name' => "a-$i",
                'task_type' => 'note.reminder',
                'priority' => 5,
                'weight' => 1,
            ]);
        }

        $bTask = $this->createActiveTaskDueAtIn($tenantA, $envB, $userA, $dueAt, [
            'name' => 'b-light',
            'task_type' => 'note.reminder',
            'priority' => 5,
            'weight' => 1,
        ]);

        $claimed = $this->app->make(DueTaskClaimer::class)->claim(4);
        $this->assertCount(4, $claimed);

        $claimedTaskIds = array_map(
            static fn ($claimedAttempt) => $claimedAttempt->run->task_id,
            $claimed,
        );
        $this->assertContains($bTask->id, $claimedTaskIds, 'Workspace B due work must be claimed within the fairness bound');

        $envACount = TaskRun::query()->where('environment_id', $envA->id)->count();
        $envBCount = TaskRun::query()->where('environment_id', $envB->id)->count();
        $this->assertSame(1, $envBCount);
        $this->assertSame(3, $envACount);
    }

    public function test_wfq_lets_light_workspace_claim_before_heavy_backlog(): void
    {
        config([
            'task_types.global_inflight_ceiling' => 10,
            'task_types.types.document.convert.concurrency_cap' => 10,
            'task_types.types.note.reminder.concurrency_cap' => 10,
            'scheduler.fairness_workspace_weight' => 1,
            'scheduler.fairness_mode' => 'wfq',
            'scheduler.priority_preemption_min' => null,
        ]);

        [$tenant, $envA, $user] = $this->createTenantContext();
        $envB = Environment::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'light-ws']);
        $dueAt = '2026-07-18T11:45:00Z';

        for ($i = 0; $i < 6; $i++) {
            $this->createActiveTaskDueAtIn($tenant, $envA, $user, $dueAt, [
                'name' => "heavy-$i",
                'task_type' => 'document.convert',
                'priority' => 5,
                'weight' => 3,
            ]);
        }

        $light = $this->createActiveTaskDueAtIn($tenant, $envB, $user, $dueAt, [
            'name' => 'light',
            'task_type' => 'note.reminder',
            'priority' => 5,
            'weight' => 1,
        ]);

        $claimed = $this->app->make(DueTaskClaimer::class)->claim(3);
        $this->assertCount(3, $claimed);
        $this->assertSame($light->id, $claimed[0]->run->task_id);
    }

    public function test_priority_preemption_claims_high_priority_workspace_first(): void
    {
        config([
            'task_types.global_inflight_ceiling' => 4,
            'task_types.types.note.reminder.concurrency_cap' => 10,
            'scheduler.fairness_workspace_weight' => 1,
            'scheduler.fairness_mode' => 'rr',
            'scheduler.priority_preemption_min' => 8,
            'scheduler.priority_preemption_slots' => 1,
        ]);

        [$tenant, $envA, $user] = $this->createTenantContext();
        $envB = Environment::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'urgent-ws']);
        $dueAt = '2026-07-18T11:45:00Z';

        for ($i = 0; $i < 5; $i++) {
            $this->createActiveTaskDueAtIn($tenant, $envA, $user, $dueAt, [
                'name' => "low-$i",
                'task_type' => 'note.reminder',
                'priority' => 3,
                'weight' => 1,
            ]);
        }

        $urgent = $this->createActiveTaskDueAtIn($tenant, $envB, $user, $dueAt, [
            'name' => 'urgent',
            'task_type' => 'note.reminder',
            'priority' => 9,
            'weight' => 1,
        ]);

        $claimed = $this->app->make(DueTaskClaimer::class)->claim(3);
        $this->assertCount(3, $claimed);
        $this->assertSame($urgent->id, $claimed[0]->run->task_id);
    }
}
