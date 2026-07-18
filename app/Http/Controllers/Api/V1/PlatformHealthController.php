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
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isPlatformAdmin()) {
            abort(403);
        }

        $databaseOk = $this->databaseOk();
        $scheduler = SystemHeartbeat::query()->where('name', 'scheduler.execute_due')->first();
        $retry = SystemHeartbeat::query()->where('name', 'scheduler.retry_due')->first();
        $staleClaims = TaskRunAttempt::query()
            ->whereNotNull('claim_token')
            ->where('claim_expires_at', '<', now())
            ->count();

        $status = $databaseOk ? 'healthy' : 'degraded';

        return response()->json([
            'status' => $status,
            'database' => $databaseOk ? 'ok' : 'error',
            'scheduler_last_seen_at' => $scheduler?->last_seen_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'retry_executor_last_seen_at' => $retry?->last_seen_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'stale_claims' => $staleClaims,
            'pending_runs' => TaskRun::query()->whereIn('run_state', ['pending', 'retry_wait', 'running'])->count(),
            'version' => config('app.version', '1.1.0'),
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
}
