<?php

namespace Tests\Feature;

use App\Application\Notifications\FailureNotifier;
use App\Application\Tasks\TaskLifecycleService;
use App\Domain\Execution\Enums\RunState;
use App\Domain\Shared\Enums\TenantRole;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use App\Infrastructure\Persistence\Eloquent\UserPreference;
use App\Mail\TaskRunFailedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class FailureNotifierLocaleTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_each_admin_gets_the_dead_run_email_in_their_own_locale(): void
    {
        Mail::fake();

        [$owner, $tenant, $environment] = $this->createTenantAdmin();

        $task = Task::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
        ]);

        $run = $this->app->make(TaskLifecycleService::class)->queueManualRun($task);
        $run->run_state = RunState::Dead;
        $run->finished_at = '2026-07-18T11:55:00Z';
        $run->save();

        $enAdmin = $owner;
        UserPreference::query()->where('user_id', $enAdmin->id)->update(['locale' => 'en']);

        $ptAdmin = User::factory()->create(['email' => 'pt-admin@example.com']);
        UserPreference::query()->where('user_id', $ptAdmin->id)->update(['locale' => 'pt-BR']);
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $ptAdmin->id,
            'role' => TenantRole::TenantAdmin,
        ]);

        app(FailureNotifier::class)->notifyDeadRun($run);

        Mail::assertSent(TaskRunFailedMail::class, function (TaskRunFailedMail $mail) use ($enAdmin) {
            return $mail->hasTo($enAdmin->email)
                && $mail->locale === 'en'
                && str_contains($mail->render(), 'Task run entered a dead state');
        });

        Mail::assertSent(TaskRunFailedMail::class, function (TaskRunFailedMail $mail) {
            return $mail->hasTo('pt-admin@example.com')
                && $mail->locale === 'pt-BR'
                && str_contains($mail->render(), 'Execução de tarefa entrou em estado morto');
        });
    }
}
