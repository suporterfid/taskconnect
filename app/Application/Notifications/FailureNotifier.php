<?php

namespace App\Application\Notifications;

use App\Application\Audit\AuditLogger;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\UserPreference;
use App\Mail\TaskRunFailedMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class FailureNotifier
{
    public function __construct(
        private readonly DeadRunWebhookSender $webhookSender,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function notifyDeadRun(TaskRun $run): void
    {
        try {
            $run->loadMissing(['environment', 'task']);
            $environment = $run->environment;

            $this->notifyEmail($run, $environment);
            $this->notifyWebhook($run, $environment);
        } catch (Throwable) {
            // Alert delivery must never unwind a settled dead run.
        }
    }

    private function notifyEmail(TaskRun $run, ?Environment $environment): void
    {
        if (! Schema::hasTable('tenant_memberships')) {
            return;
        }

        if (! config('scheduler.failure_emails_enabled', true)) {
            return;
        }

        if ($environment !== null && $environment->dead_run_email_enabled === false) {
            return;
        }

        $admins = User::query()
            ->whereHas('memberships', function ($q) use ($run) {
                $q->where('tenant_id', $run->tenant_id)
                    ->where('role', 'tenant_admin');
            })
            ->with('preferences')
            ->get();

        $sent = 0;
        foreach ($admins as $admin) {
            $prefs = $admin->preferences;
            if ($prefs instanceof UserPreference && $prefs->failure_emails_enabled === false) {
                continue;
            }

            Mail::to($admin->email)->send(new TaskRunFailedMail($run));
            $sent++;
        }

        if ($sent > 0) {
            $this->auditLogger->log(
                action: 'dlq.alert.email_sent',
                resourceType: 'task_run',
                resourceId: $run->public_id,
                tenantId: $run->tenant_id,
                environmentId: $run->environment_id,
                summary: [
                    'channel' => 'email',
                    'recipient_count' => $sent,
                ],
            );
        }
    }

    private function notifyWebhook(TaskRun $run, ?Environment $environment): void
    {
        if (! config('scheduler.failure_webhooks_enabled', true)) {
            return;
        }

        if ($environment === null) {
            return;
        }

        if ($environment->dead_run_webhook_enabled !== true) {
            return;
        }

        if ($environment->dead_run_webhook_url === null || trim($environment->dead_run_webhook_url) === '') {
            return;
        }

        $result = $this->webhookSender->send($run, $environment);

        $this->auditLogger->log(
            action: $result['ok'] ? 'dlq.alert.webhook_sent' : 'dlq.alert.webhook_failed',
            resourceType: 'task_run',
            resourceId: $run->public_id,
            tenantId: $run->tenant_id,
            environmentId: $run->environment_id,
            summary: [
                'channel' => 'webhook',
                'status_code' => $result['status_code'],
                'webhook_host' => $result['host'],
                'reason' => $result['reason'],
            ],
        );
    }
}
