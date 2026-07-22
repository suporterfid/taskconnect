<?php

namespace App\Console\Commands;

use App\Application\Scheduling\SchedulerCycleRunner;
use App\Application\Scheduling\StaleClaimRecovery;
use App\Application\Scheduling\HeartbeatWriter;
use Illuminate\Console\Command;

class ExecuteDueCommand extends Command
{
    protected $signature = 'scheduler:execute-due';

    protected $description = 'Claim and execute due scheduled tasks';

    public function handle(SchedulerCycleRunner $runner): int
    {
        $stats = $runner->executeDue();

        $this->info(sprintf(
            'Claimed %d due task(s); %d succeeded, %d failed (%d ms)%s.',
            $stats['claimed'],
            $stats['successful'],
            $stats['failed'],
            $stats['duration_ms'],
            ! empty($stats['budget_stopped']) ? '; stopped on wall-clock budget' : '',
        ));

        return self::SUCCESS;
    }
}
