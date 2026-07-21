<?php

namespace App\Application\Scheduling;

use App\Domain\Shared\Clock;

final class SchedulerCycleRunner
{
    public function __construct(
        private readonly Clock $clock,
        private readonly DueTaskClaimer $dueTaskClaimer,
        private readonly PendingRunClaimer $pendingRunClaimer,
        private readonly RetryClaimer $retryClaimer,
        private readonly AttemptExecutor $attemptExecutor,
        private readonly HeartbeatWriter $heartbeatWriter,
    ) {
    }

    /**
     * @return array<string, int|float>
     */
    public function executeDue(): array
    {
        $started = microtime(true);
        $batchSize = (int) config('scheduler.claim_batch', 20);
        $claimed = $this->dueTaskClaimer->claim($batchSize);
        $successful = 0;
        $failed = 0;

        foreach ($claimed as $claimedAttempt) {
            try {
                $this->attemptExecutor->execute($claimedAttempt->attempt);
                $successful++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $pendingClaimed = $this->pendingRunClaimer->claim($batchSize);
        $pendingSuccessful = 0;
        $pendingFailed = 0;

        foreach ($pendingClaimed as $claimedAttempt) {
            try {
                $this->attemptExecutor->execute($claimedAttempt->attempt);
                $pendingSuccessful++;
            } catch (\Throwable) {
                $pendingFailed++;
            }
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $this->heartbeatWriter->record('scheduler.execute_due', [
            'claimed' => count($claimed),
            'successful' => $successful,
            'failed' => $failed,
            'pending_claimed' => count($pendingClaimed),
            'pending_successful' => $pendingSuccessful,
            'pending_failed' => $pendingFailed,
            'duration_ms' => $durationMs,
        ]);

        return [
            'claimed' => count($claimed),
            'successful' => $successful,
            'failed' => $failed,
            'pending_claimed' => count($pendingClaimed),
            'pending_successful' => $pendingSuccessful,
            'pending_failed' => $pendingFailed,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function retryDue(): array
    {
        $started = microtime(true);
        $batchSize = (int) config('scheduler.retry_batch', 20);
        $claimed = $this->retryClaimer->claim($batchSize);
        $successful = 0;
        $failed = 0;

        foreach ($claimed as $claimedAttempt) {
            try {
                $this->attemptExecutor->execute($claimedAttempt->attempt);
                $successful++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $this->heartbeatWriter->record('scheduler.retry_due', [
            'claimed' => count($claimed),
            'successful' => $successful,
            'failed' => $failed,
            'duration_ms' => $durationMs,
        ]);

        return [
            'claimed' => count($claimed),
            'successful' => $successful,
            'failed' => $failed,
            'duration_ms' => $durationMs,
        ];
    }
}
