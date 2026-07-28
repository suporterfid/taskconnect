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
            subject: 'TaskConnect: task run entered dead state',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.task-run-failed-html',
            text: 'mail.task-run-failed',
            with: [
                'runId' => $this->run->public_id,
                'taskId' => $this->run->task_id,
                'taskName' => $this->run->task?->name,
                'state' => $this->run->run_state,
                'error' => $this->run->final_error_code,
                'runUrl' => rtrim(config('app.url'), '/').'/runs/'.$this->run->public_id,
            ],
        );
    }
}
