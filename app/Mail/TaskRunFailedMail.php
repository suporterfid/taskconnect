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
            text: 'mail.task-run-failed',
            with: [
                'runId' => $this->run->public_id,
                'taskId' => $this->run->task_id,
                'state' => $this->run->run_state,
                'error' => $this->run->final_error_code,
            ],
        );
    }
}
