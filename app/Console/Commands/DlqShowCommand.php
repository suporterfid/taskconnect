<?php

namespace App\Console\Commands;

use App\Application\Tasks\DlqService;
use Illuminate\Console\Command;

class DlqShowCommand extends Command
{
    protected $signature = 'tasks:dlq:show {public_id : Dead run public id}';

    protected $description = 'Inspect a dead-letter run (error, response snippet, attempts)';

    public function handle(DlqService $dlq): int
    {
        $run = $dlq->find((string) $this->argument('public_id'));

        if ($run === null) {
            $this->error('Dead-letter run not found.');

            return self::FAILURE;
        }

        $this->line('id: '.$run->public_id);
        $this->line('workspace: '.($run->environment?->public_id ?? '-'));
        $this->line('task: '.($run->task?->public_id ?? '-'));
        $this->line('task_type: '.($run->task?->task_type ?? 'default'));
        $this->line('run_state: '.$run->run_state->value);
        $this->line('attempt_count: '.$run->attempt_count);
        $this->line('final_error_code: '.($run->final_error_code ?? '-'));
        $this->line('final_http_status: '.($run->final_http_status ?? '-'));
        $this->line('idempotency_key: '.$run->idempotency_key);
        $this->line('finished_at: '.($run->finished_at?->utc()->format('Y-m-d\TH:i:s\Z') ?? '-'));
        $this->newLine();
        $this->info('Attempts:');

        foreach ($run->attempts as $attempt) {
            $this->line(sprintf(
                '  #%d state=%s http=%s transport=%s duration_ms=%s',
                $attempt->attempt_number,
                $attempt->attempt_state->value,
                $attempt->response_status ?? '-',
                $attempt->transport_error_code ?? '-',
                $attempt->duration_ms ?? '-',
            ));
            if ($attempt->transport_error_message) {
                $this->line('    transport_message: '.$attempt->transport_error_message);
            }
            if ($attempt->response_body_truncated) {
                $snippet = mb_substr((string) $attempt->response_body_truncated, 0, 400);
                $this->line('    response_snippet: '.$snippet);
            }
        }

        return self::SUCCESS;
    }
}
