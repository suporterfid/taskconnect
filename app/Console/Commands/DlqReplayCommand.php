<?php

namespace App\Console\Commands;

use App\Application\Tasks\DlqService;
use Illuminate\Console\Command;

class DlqReplayCommand extends Command
{
    protected $signature = 'tasks:dlq:replay
                            {public_id? : Dead run public id}
                            {--type= : Replay all dead runs of this task_type}
                            {--workspace= : Limit --type replay to a workspace}
                            {--limit=50 : Max runs when using --type}';

    protected $description = 'Replay dead-letter run(s) with a fresh delivery Idempotency-Key group';

    public function handle(DlqService $dlq): int
    {
        $publicId = $this->argument('public_id');
        $type = $this->option('type');

        if (($publicId === null || $publicId === '') === ($type === null || $type === '')) {
            $this->error('Provide exactly one of {public_id} or --type=.');

            return self::FAILURE;
        }

        if ($publicId !== null && $publicId !== '') {
            $dead = $dlq->find((string) $publicId);
            if ($dead === null) {
                $this->error('Dead-letter run not found.');

                return self::FAILURE;
            }

            $newRun = $dlq->replay($dead);
            $this->info(sprintf(
                'Replayed %s → new run %s (idempotency_key=%s).',
                $dead->public_id,
                $newRun->public_id,
                $newRun->idempotency_key,
            ));

            return self::SUCCESS;
        }

        $replayed = $dlq->replayByType(
            taskType: (string) $type,
            workspacePublicId: $this->option('workspace') ?: null,
            limit: (int) $this->option('limit'),
        );

        if ($replayed === []) {
            $this->info('No matching dead-letter runs to replay.');

            return self::SUCCESS;
        }

        foreach ($replayed as $newRun) {
            $this->line(sprintf('new run %s (task=%s)', $newRun->public_id, $newRun->task?->public_id ?? '-'));
        }

        $this->info(sprintf('Replayed %d dead-letter run(s).', count($replayed)));

        return self::SUCCESS;
    }
}
