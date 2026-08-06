<?php

namespace Tests\Unit\Mail;

use App\Domain\Execution\Enums\RunState;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Mail\TaskRunFailedMail;
use Tests\TestCase;

class TaskRunFailedMailTest extends TestCase
{
    public function test_renders_task_name_and_a_link_back_to_the_run_in_both_formats(): void
    {
        config(['app.url' => 'https://taskconnect.example']);

        $task = new Task(['name' => 'Nightly export']);

        $run = new TaskRun([
            'public_id' => 'run_test123',
            'task_id' => 'task_test456',
            'run_state' => RunState::Dead,
            'final_error_code' => 'timeout',
        ]);
        $run->setRelation('task', $task);

        $mail = new TaskRunFailedMail($run);

        $html = $mail->render();
        $this->assertStringContainsString('Nightly export', $html);
        $this->assertStringContainsString('run_test123', $html);
        $this->assertStringContainsString('https://taskconnect.example/runs/run_test123', $html);
        $this->assertStringContainsString('timeout', $html);

        $textContent = view('mail.task-run-failed', [
            'taskRunLine' => __('mail.task_run_line', [
                'runId' => 'run_test123',
                'taskName' => 'Nightly export',
                'state' => RunState::Dead->value,
            ]),
            'errorCodeLine' => __('mail.error_code_line', ['error' => 'timeout']),
            'viewRunLine' => __('mail.view_run_line', ['runUrl' => 'https://taskconnect.example/runs/run_test123']),
            'diagnosticsLine' => __('mail.diagnostics_line'),
        ])->render();

        $this->assertStringContainsString('Nightly export', $textContent);
        $this->assertStringContainsString('https://taskconnect.example/runs/run_test123', $textContent);
    }

    public function test_falls_back_to_the_task_id_when_the_task_relation_is_unavailable(): void
    {
        $run = new TaskRun([
            'public_id' => 'run_test789',
            'task_id' => 'task_missing',
            'run_state' => RunState::Dead,
        ]);
        $run->setRelation('task', null);

        $mail = new TaskRunFailedMail($run);

        $html = $mail->render();
        $this->assertStringContainsString('#task_missing', $html);
    }
}
