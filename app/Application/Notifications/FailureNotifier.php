<?php

namespace App\Application\Notifications;

use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\UserPreference;
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
            ->with('preferences')
            ->get();

        foreach ($admins as $admin) {
            $prefs = $admin->preferences;
            if ($prefs instanceof UserPreference && $prefs->failure_emails_enabled === false) {
                continue;
            }

            Mail::to($admin->email)->send(new TaskRunFailedMail($run));
        }
    }
}
