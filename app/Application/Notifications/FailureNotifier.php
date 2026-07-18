<?php

namespace App\Application\Notifications;

use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Mail\TaskRunFailedMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

final class FailureNotifier
{
    public function notifyDeadRun(TaskRun $run): void
    {
        if (! Schema::hasTable('tenant_memberships')) {
            return;
        }

        if (! config('scheduler.failure_emails_enabled', true)) {
            return;
        }

        $admins = User::query()
            ->whereHas('memberships', function ($q) use ($run) {
                $q->where('tenant_id', $run->tenant_id)
                    ->where('role', 'tenant_admin');
            })
            ->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new TaskRunFailedMail($run));
        }
    }
}
