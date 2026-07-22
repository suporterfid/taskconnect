<?php

namespace App\Application\Scheduling;

use App\Application\Audit\AuditLogger;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;

/**
 * Workspace-scoped audit for scheduler claim + delivery outcomes (S10).
 * Summaries stay metadata-only (no request/response bodies).
 */
final class SchedulerAuditRecorder
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function recordClaim(ClaimedAttempt $claimed, string $source): void
    {
        if (! filter_var(config('scheduler.audit_claims', true), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $run = $claimed->run;
        $attempt = $claimed->attempt;
        $task = $run->relationLoaded('task') ? $run->task : $run->task()->first();

        $this->auditLogger->log(
            action: 'scheduler.claim',
            resourceType: 'task_run',
            resourceId: $run->public_id,
            tenantId: (int) $run->tenant_id,
            environmentId: (int) $run->environment_id,
            summary: [
                'source' => $source,
                'attempt_number' => (int) $attempt->attempt_number,
                'task_id' => $task?->public_id,
                'task_type' => $task?->task_type,
                'trigger_type' => $run->trigger_type instanceof \BackedEnum
                    ? $run->trigger_type->value
                    : (string) $run->trigger_type,
            ],
        );
    }

    public function recordDeliveryOutcome(
        TaskRun $run,
        TaskRunAttempt $attempt,
        Task $task,
        string $outcome,
        ?int $httpStatus = null,
        ?string $errorCode = null,
    ): void {
        if (! filter_var(config('scheduler.audit_deliveries', true), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $this->auditLogger->log(
            action: 'scheduler.delivery',
            resourceType: 'task_run',
            resourceId: $run->public_id,
            tenantId: (int) $run->tenant_id,
            environmentId: (int) $run->environment_id,
            summary: [
                'outcome' => $outcome,
                'attempt_number' => (int) $attempt->attempt_number,
                'http_status' => $httpStatus,
                'error_code' => $errorCode,
                'task_id' => $task->public_id,
                'task_type' => $task->task_type,
            ],
        );
    }
}
