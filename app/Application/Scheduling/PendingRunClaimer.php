<?php

namespace App\Application\Scheduling;

use App\Domain\Execution\Enums\AttemptState;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PendingRunClaimer
{
    public function __construct(
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return list<ClaimedAttempt>
     */
    public function claim(int $batchSize): array
    {
        $now = $this->clock->nowUtc();
        $claimed = [];

        DB::transaction(function () use ($batchSize, $now, &$claimed): void {
            $runs = $this->selectPendingRuns($batchSize);

            foreach ($runs as $run) {
                $attempt = $this->tryClaimPending($run, $now);

                if ($attempt !== null) {
                    $claimed[] = new ClaimedAttempt($run->fresh(['task']), $attempt);
                }
            }
        });

        return $claimed;
    }

    /**
     * @return list<TaskRun>
     */
    private function selectPendingRuns(int $batchSize): array
    {
        $driver = DB::connection()->getDriverName();

        $query = TaskRun::query()
            ->where('run_state', RunState::Pending)
            ->orderBy('created_at')
            ->limit($batchSize);

        if ($driver === 'mysql') {
            $query->lock('FOR UPDATE SKIP LOCKED');
        } else {
            $query->lockForUpdate();
        }

        return $query->with('task')->get()->all();
    }

    private function tryClaimPending(TaskRun $run, DateTimeImmutable $now): ?TaskRunAttempt
    {
        $nowString = $now->format('Y-m-d H:i:s');

        $attempt = TaskRunAttempt::query()
            ->where('task_run_id', $run->id)
            ->where('attempt_state', AttemptState::Pending)
            ->orderByDesc('attempt_number')
            ->lockForUpdate()
            ->first();

        if ($attempt === null) {
            $attempt = $this->createMissingPendingAttempt($run);

            if ($attempt === null) {
                return null;
            }
        }

        if ($attempt->hasActiveClaim($now)) {
            return null;
        }

        $claimToken = (string) Str::uuid();
        $claimExpiresAt = $now->modify(sprintf('+%d minutes', (int) config('scheduler.claim_ttl_minutes', 10)));

        $updated = TaskRunAttempt::query()
            ->where('id', $attempt->id)
            ->where('attempt_state', AttemptState::Pending)
            ->where(function ($builder) use ($nowString): void {
                $builder->whereNull('claim_token')
                    ->orWhere('claim_expires_at', '<', $nowString);
            })
            ->update([
                'claim_token' => $claimToken,
                'claimed_at' => $nowString,
                'claim_expires_at' => $claimExpiresAt->format('Y-m-d H:i:s'),
            ]);

        if ($updated === 0) {
            return null;
        }

        return $attempt->fresh();
    }

    private function createMissingPendingAttempt(TaskRun $run): ?TaskRunAttempt
    {
        $maxNumber = (int) TaskRunAttempt::query()
            ->where('task_run_id', $run->id)
            ->max('attempt_number');

        if ($maxNumber > 0) {
            // A non-pending latest attempt exists; do not invent another.
            return null;
        }

        $attempt = TaskRunAttempt::query()->create([
            'tenant_id' => $run->tenant_id,
            'environment_id' => $run->environment_id,
            'task_run_id' => $run->id,
            'attempt_number' => 1,
            'attempt_state' => AttemptState::Pending,
        ]);

        if ((int) $run->attempt_count < 1) {
            $run->attempt_count = 1;
            $run->save();
        }

        return $attempt;
    }
}
