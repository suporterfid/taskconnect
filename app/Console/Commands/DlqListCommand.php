<?php

namespace App\Console\Commands;

use App\Application\Tasks\DlqService;
use Illuminate\Console\Command;

class DlqListCommand extends Command
{
    protected $signature = 'tasks:dlq:list
                            {--workspace= : Environment/workspace public id}
                            {--type= : Filter by task_type}
                            {--limit=50 : Max rows}';

    protected $description = 'List dead-letter runs (run_state=dead)';

    public function handle(DlqService $dlq): int
    {
        $rows = $dlq->list(
            workspacePublicId: $this->option('workspace') ?: null,
            taskType: $this->option('type') ?: null,
            limit: (int) $this->option('limit'),
        );

        if ($rows->isEmpty()) {
            $this->info('No dead-letter runs found.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'workspace', 'task', 'task_type', 'error', 'http', 'finished_at', 'attempts'],
            $rows->map(static function ($run): array {
                return [
                    $run->public_id,
                    $run->environment?->public_id ?? '-',
                    $run->task?->public_id ?? '-',
                    $run->task?->task_type ?? 'default',
                    $run->final_error_code ?? '-',
                    $run->final_http_status ?? '-',
                    $run->finished_at?->utc()->format('Y-m-d\TH:i:s\Z') ?? '-',
                    $run->attempt_count,
                ];
            })->all(),
        );

        return self::SUCCESS;
    }
}
