<?php

namespace App\Console\Commands;

use App\Application\Scheduling\SchedulerCycleRunner;
use Illuminate\Console\Command;

class RetryDueCommand extends Command
{
    protected $signature = 'scheduler:retry-due';

    protected $description = 'Claim and execute due retry attempts';

    public function handle(SchedulerCycleRunner $runner): int
    {
        $stats = $runner->retryDue();

        $this->info(sprintf(
            'Claimed %d retry run(s); %d succeeded, %d failed (%d ms).',
            $stats['claimed'],
            $stats['successful'],
            $stats['failed'],
            $stats['duration_ms'],
        ));

        return self::SUCCESS;
    }
}
