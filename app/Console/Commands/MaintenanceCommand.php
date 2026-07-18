<?php

namespace App\Console\Commands;

use App\Application\Retention\RetentionCleaner;
use App\Application\Scheduling\HeartbeatWriter;
use App\Application\Scheduling\StaleClaimRecovery;
use Illuminate\Console\Command;

class MaintenanceCommand extends Command
{
    protected $signature = 'scheduler:maintenance';

    protected $description = 'Recover stale claims and perform retention maintenance';

    public function handle(
        StaleClaimRecovery $recovery,
        RetentionCleaner $cleaner,
        HeartbeatWriter $heartbeatWriter,
    ): int {
        $recovered = $recovery->recover();
        $cleanup = $cleaner->run();

        $heartbeatWriter->record('scheduler.maintenance', [
            'stale_claims_recovered' => $recovered,
            'cleanup' => $cleanup,
        ]);

        $this->info(sprintf('Recovered %d stale claim(s).', $recovered));
        foreach ($cleanup as $key => $count) {
            $this->line(sprintf('%s: %d', $key, $count));
        }

        return self::SUCCESS;
    }
}
