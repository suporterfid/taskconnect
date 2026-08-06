<?php

namespace App\Mail;

use App\Infrastructure\Persistence\Eloquent\TaskRun;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskRunFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TaskRun $run)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.subject'),
        );
    }

    public function content(): Content
    {
        $runId = $this->run->public_id;
        $taskDisplay = $this->run->task?->name ?? '#'.$this->run->task_id;
        $state = $this->run->run_state;
        $errorDisplay = $this->run->final_error_code ?? __('mail.error_na');
        $runUrl = rtrim(config('app.url'), '/').'/runs/'.$this->run->public_id;

        return new Content(
            html: 'mail.task-run-failed-html',
            text: 'mail.task-run-failed',
            with: [
                'runId' => $runId,
                'taskDisplay' => $taskDisplay,
                'state' => $state,
                'errorDisplay' => $errorDisplay,
                'runUrl' => $runUrl,
                'heading' => __('mail.heading'),
                'taskLabel' => __('mail.task_label'),
                'runLabel' => __('mail.run_label'),
                'stateLabel' => __('mail.state_label'),
                'errorCodeLabel' => __('mail.error_code_label'),
                'viewRunButton' => __('mail.view_run_button'),
                'diagnosticsNote' => __('mail.diagnostics_note'),
                'taskRunLine' => __('mail.task_run_line', ['runId' => $runId, 'taskName' => $taskDisplay, 'state' => $state]),
                'errorCodeLine' => __('mail.error_code_line', ['error' => $errorDisplay]),
                'viewRunLine' => __('mail.view_run_line', ['runUrl' => $runUrl]),
                'diagnosticsLine' => __('mail.diagnostics_line'),
            ],
        );
    }
}
