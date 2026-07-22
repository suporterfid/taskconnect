<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\SystemHeartbeat;
use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformHealthController extends Controller
{
    private const STALE_SECONDS = 120;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isPlatformAdmin()) {
            abort(403);
        }

        $databaseOk = $this->databaseOk();
        $scheduler = SystemHeartbeat::query()->where('name', 'scheduler.execute_due')->first();
        $retry = SystemHeartbeat::query()->where('name', 'scheduler.retry_due')->first();
        $maintenance = SystemHeartbeat::query()->where('name', 'scheduler.maintenance')->first();

        $schedulerStale = $this->isStale($scheduler?->last_seen_at);
        $retryStale = $this->isStale($retry?->last_seen_at);

        $staleClaims = TaskRunAttempt::query()
            ->whereNotNull('claim_token')
            ->where('claim_expires_at', '<', now())
            ->count();

        $status = ($databaseOk && ! $schedulerStale && ! $retryStale) ? 'healthy' : 'degraded';

        return response()->json([
            'status' => $status,
            'database' => $databaseOk ? 'ok' : 'error',
            'scheduler_last_seen_at' => $scheduler?->last_seen_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'retry_executor_last_seen_at' => $retry?->last_seen_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'maintenance_last_seen_at' => $maintenance?->last_seen_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'scheduler_stale' => $schedulerStale,
            'retry_executor_stale' => $retryStale,
            'stale_claims' => $staleClaims,
            'pending_runs' => TaskRun::query()->whereIn('run_state', ['pending', 'retry_wait', 'running'])->count(),
            'version' => config('app.version', '1.2.0'),
            'retention' => [
                'payload_snapshots_days' => (int) config('retention.payload_snapshots_days'),
                'attempt_metadata_days' => (int) config('retention.attempt_metadata_days'),
                'run_summary_days' => (int) config('retention.run_summary_days'),
                'audit_logs_days' => (int) config('retention.audit_logs_days'),
                'api_idempotency_hours' => (int) config('retention.api_idempotency_hours'),
                'system_heartbeat_days' => (int) config('retention.system_heartbeat_days'),
                'dead_runs_days' => (int) config('retention.dead_runs_days'),
            ],
        ]);
    }

    private function databaseOk(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function isStale(mixed $lastSeen): bool
    {
        if ($lastSeen === null) {
            return true;
        }

        return $lastSeen->lt(now()->subSeconds(self::STALE_SECONDS));
    }
}
