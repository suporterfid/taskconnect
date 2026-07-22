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
     * @return array<string, int|float|bool>
     */
    public function executeDue(?TickBudget $budget = null): array
    {
        $started = microtime(true);
        $budget ??= TickBudget::fromConfig();
        $batchSize = (int) config('scheduler.claim_batch', 20);
        $chunkSize = max(1, (int) config('scheduler.claim_chunk', 5));

        $claimedCount = 0;
        $successful = 0;
        $failed = 0;
        $budgetStopped = false;

        while ($claimedCount < $batchSize) {
            if (! $budget->canClaimMore()) {
                $budgetStopped = true;
                break;
            }

            $toClaim = min($chunkSize, $batchSize - $claimedCount);
            $claimed = $this->dueTaskClaimer->claim($toClaim);
            if ($claimed === []) {
                break;
            }

            foreach ($claimed as $claimedAttempt) {
                $claimedCount++;
                try {
                    $this->attemptExecutor->execute($claimedAttempt->attempt);
                    $successful++;
                } catch (\Throwable) {
                    $failed++;
                }
            }

            if (! $budget->canClaimMore()) {
                $budgetStopped = true;
                break;
            }
        }

        $pendingClaimedCount = 0;
        $pendingSuccessful = 0;
        $pendingFailed = 0;

        while ($pendingClaimedCount < $batchSize) {
            if (! $budget->canClaimMore()) {
                $budgetStopped = true;
                break;
            }

            $toClaim = min($chunkSize, $batchSize - $pendingClaimedCount);
            $pendingClaimed = $this->pendingRunClaimer->claim($toClaim);
            if ($pendingClaimed === []) {
                break;
            }

            foreach ($pendingClaimed as $claimedAttempt) {
                $pendingClaimedCount++;
                try {
                    $this->attemptExecutor->execute($claimedAttempt->attempt);
                    $pendingSuccessful++;
                } catch (\Throwable) {
                    $pendingFailed++;
                }
            }

            if (! $budget->canClaimMore()) {
                $budgetStopped = true;
                break;
            }
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $this->heartbeatWriter->record('scheduler.execute_due', [
            'claimed' => $claimedCount,
            'successful' => $successful,
            'failed' => $failed,
            'pending_claimed' => $pendingClaimedCount,
            'pending_successful' => $pendingSuccessful,
            'pending_failed' => $pendingFailed,
            'duration_ms' => $durationMs,
            'budget_seconds' => $budget->limitSeconds(),
            'budget_stopped' => $budgetStopped,
        ]);

        return [
            'claimed' => $claimedCount,
            'successful' => $successful,
            'failed' => $failed,
            'pending_claimed' => $pendingClaimedCount,
            'pending_successful' => $pendingSuccessful,
            'pending_failed' => $pendingFailed,
            'duration_ms' => $durationMs,
            'budget_seconds' => $budget->limitSeconds(),
            'budget_stopped' => $budgetStopped,
        ];
    }

    /**
     * @return array<string, int|float|bool>
     */
    public function retryDue(?TickBudget $budget = null): array
    {
        $started = microtime(true);
        $budget ??= TickBudget::fromConfig();
        $batchSize = (int) config('scheduler.retry_batch', 20);
        $chunkSize = max(1, (int) config('scheduler.claim_chunk', 5));

        $claimedCount = 0;
        $successful = 0;
        $failed = 0;
        $budgetStopped = false;

        while ($claimedCount < $batchSize) {
            if (! $budget->canClaimMore()) {
                $budgetStopped = true;
                break;
            }

            $toClaim = min($chunkSize, $batchSize - $claimedCount);
            $claimed = $this->retryClaimer->claim($toClaim);
            if ($claimed === []) {
                break;
            }

            foreach ($claimed as $claimedAttempt) {
                $claimedCount++;
                try {
                    $this->attemptExecutor->execute($claimedAttempt->attempt);
                    $successful++;
                } catch (\Throwable) {
                    $failed++;
                }
            }

            if (! $budget->canClaimMore()) {
                $budgetStopped = true;
                break;
            }
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $this->heartbeatWriter->record('scheduler.retry_due', [
            'claimed' => $claimedCount,
            'successful' => $successful,
            'failed' => $failed,
            'duration_ms' => $durationMs,
            'budget_seconds' => $budget->limitSeconds(),
            'budget_stopped' => $budgetStopped,
        ]);

        return [
            'claimed' => $claimedCount,
            'successful' => $successful,
            'failed' => $failed,
            'duration_ms' => $durationMs,
            'budget_seconds' => $budget->limitSeconds(),
            'budget_stopped' => $budgetStopped,
        ];
    }
}
