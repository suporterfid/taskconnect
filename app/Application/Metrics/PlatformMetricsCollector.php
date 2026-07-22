<?php

namespace App\Application\Metrics;

use App\Infrastructure\Persistence\Eloquent\SystemHeartbeat;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use Illuminate\Support\Facades\DB;

/**
 * Collects platform gauges for Prometheus-style exposition (R18).
 * Shared-hosting friendly: MySQL aggregates + bounded sample for latencies; no sidecar.
 */
final class PlatformMetricsCollector
{
    private const LATENCY_SAMPLE_LIMIT = 500;

    /**
     * @return list<string> Prometheus text exposition lines (without trailing blank required by scraper)
     */
    public function renderPrometheus(): string
    {
        $lines = [];

        $queueByState = TaskRun::query()
            ->select('run_state', DB::raw('COUNT(*) as c'))
            ->whereIn('run_state', ['pending', 'retry_wait', 'blocked'])
            ->groupBy('run_state')
            ->pluck('c', 'run_state');

        $lines[] = '# HELP taskconnect_queue_depth Runs waiting to execute (by state)';
        $lines[] = '# TYPE taskconnect_queue_depth gauge';
        foreach (['pending', 'retry_wait', 'blocked'] as $state) {
            $lines[] = sprintf(
                'taskconnect_queue_depth{state="%s"} %d',
                $state,
                (int) ($queueByState[$state] ?? 0),
            );
        }

        $inflightTotal = TaskRun::query()->where('run_state', 'running')->count();
        $lines[] = '# HELP taskconnect_inflight Currently running runs';
        $lines[] = '# TYPE taskconnect_inflight gauge';
        $lines[] = 'taskconnect_inflight '.$inflightTotal;

        $inflightByType = TaskRun::query()
            ->join('tasks', 'tasks.id', '=', 'task_runs.task_id')
            ->where('task_runs.run_state', 'running')
            ->select(DB::raw("COALESCE(tasks.task_type, 'default') as task_type"), DB::raw('COUNT(*) as c'))
            ->groupBy('task_type')
            ->pluck('c', 'task_type');

        $lines[] = '# HELP taskconnect_inflight_by_type Currently running runs by task type';
        $lines[] = '# TYPE taskconnect_inflight_by_type gauge';
        foreach ($inflightByType as $type => $count) {
            $lines[] = sprintf(
                'taskconnect_inflight_by_type{task_type="%s"} %d',
                $this->escapeLabel((string) $type),
                (int) $count,
            );
        }

        $dlq = TaskRun::query()->where('run_state', 'dead')->count();
        $lines[] = '# HELP taskconnect_dlq_size Dead-letter runs';
        $lines[] = '# TYPE taskconnect_dlq_size gauge';
        $lines[] = 'taskconnect_dlq_size '.$dlq;

        $this->appendLatencyMetrics($lines);
        $this->appendSchedulerTickMetrics($lines);

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendLatencyMetrics(array &$lines): void
    {
        $rows = TaskRunAttempt::query()
            ->join('task_runs', 'task_runs.id', '=', 'task_run_attempts.task_run_id')
            ->join('tasks', 'tasks.id', '=', 'task_runs.task_id')
            ->whereNotNull('task_run_attempts.duration_ms')
            ->where('task_run_attempts.duration_ms', '>', 0)
            ->orderByDesc('task_run_attempts.id')
            ->limit(self::LATENCY_SAMPLE_LIMIT)
            ->get([
                DB::raw("COALESCE(tasks.task_type, 'default') as task_type"),
                'task_run_attempts.duration_ms',
            ]);

        /** @var array<string, list<int>> $byType */
        $byType = [];
        foreach ($rows as $row) {
            $type = (string) ($row->task_type ?? 'default');
            $byType[$type][] = (int) $row->duration_ms;
        }

        $lines[] = '# HELP taskconnect_attempt_duration_ms Approximate attempt duration from recent samples';
        $lines[] = '# TYPE taskconnect_attempt_duration_ms summary';

        if ($byType === []) {
            $lines[] = 'taskconnect_attempt_duration_ms{task_type="default",quantile="0.5"} 0';
            $lines[] = 'taskconnect_attempt_duration_ms{task_type="default",quantile="0.95"} 0';
            $lines[] = 'taskconnect_attempt_duration_ms_count{task_type="default"} 0';

            return;
        }

        foreach ($byType as $type => $samples) {
            sort($samples);
            $label = $this->escapeLabel($type);
            $lines[] = sprintf(
                'taskconnect_attempt_duration_ms{task_type="%s",quantile="0.5"} %d',
                $label,
                $this->percentile($samples, 0.5),
            );
            $lines[] = sprintf(
                'taskconnect_attempt_duration_ms{task_type="%s",quantile="0.95"} %d',
                $label,
                $this->percentile($samples, 0.95),
            );
            $lines[] = sprintf(
                'taskconnect_attempt_duration_ms_count{task_type="%s"} %d',
                $label,
                count($samples),
            );
        }
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendSchedulerTickMetrics(array &$lines): void
    {
        $budgetConfigured = (float) config('scheduler.target_duration_seconds', 45);

        $lines[] = '# HELP taskconnect_scheduler_configured_budget_seconds Configured wall-clock tick budget';
        $lines[] = '# TYPE taskconnect_scheduler_configured_budget_seconds gauge';
        $lines[] = 'taskconnect_scheduler_configured_budget_seconds '.$this->formatFloat($budgetConfigured);

        $lines[] = '# HELP taskconnect_scheduler_tick_duration_seconds Last recorded tick duration';
        $lines[] = '# TYPE taskconnect_scheduler_tick_duration_seconds gauge';
        $lines[] = '# HELP taskconnect_scheduler_tick_budget_seconds Budget applied on last tick';
        $lines[] = '# TYPE taskconnect_scheduler_tick_budget_seconds gauge';
        $lines[] = '# HELP taskconnect_scheduler_budget_stopped 1 if last tick stopped early due to budget';
        $lines[] = '# TYPE taskconnect_scheduler_budget_stopped gauge';

        foreach ([
            'scheduler.execute_due' => 'execute_due',
            'scheduler.retry_due' => 'retry_due',
        ] as $heartbeatName => $command) {
            $hb = SystemHeartbeat::query()->where('name', $heartbeatName)->first();
            $meta = is_array($hb?->meta_json) ? $hb->meta_json : [];
            $durationMs = isset($meta['duration_ms']) ? (float) $meta['duration_ms'] : 0.0;
            $budgetStopped = ! empty($meta['budget_stopped']) ? 1 : 0;
            $budgetSeconds = isset($meta['budget_seconds'])
                ? (float) $meta['budget_seconds']
                : $budgetConfigured;

            $lines[] = sprintf(
                'taskconnect_scheduler_tick_duration_seconds{command="%s"} %s',
                $command,
                $this->formatFloat($durationMs / 1000.0),
            );
            $lines[] = sprintf(
                'taskconnect_scheduler_tick_budget_seconds{command="%s"} %s',
                $command,
                $this->formatFloat($budgetSeconds),
            );
            $lines[] = sprintf(
                'taskconnect_scheduler_budget_stopped{command="%s"} %d',
                $command,
                $budgetStopped,
            );
        }
    }

    /**
     * @param  list<int>  $sorted
     */
    private function percentile(array $sorted, float $p): int
    {
        if ($sorted === []) {
            return 0;
        }

        $n = count($sorted);
        $index = (int) ceil($p * $n) - 1;
        $index = max(0, min($n - 1, $index));

        return $sorted[$index];
    }

    private function escapeLabel(string $value): string
    {
        return str_replace(['\\', "\n", '"'], ['\\\\', '\\n', '\\"'], $value);
    }

    private function formatFloat(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6f', $value), '0'), '.') ?: '0';
    }
}
