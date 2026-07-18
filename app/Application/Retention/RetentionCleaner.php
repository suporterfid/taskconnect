<?php

namespace App\Application\Retention;

use App\Infrastructure\Persistence\Eloquent\SystemHeartbeat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RetentionCleaner
{
    /**
     * @return array<string, int>
     */
    public function run(int $batchSize = 500): array
    {
        $counts = [
            'payload_snapshots_cleared' => 0,
            'idempotency_keys_deleted' => 0,
            'password_reset_tokens_deleted' => 0,
            'heartbeats_pruned' => 0,
            'stale_claims_released' => 0,
        ];

        $snapshotDays = (int) config('retention.payload_snapshots_days', 30);
        $heartbeatDays = (int) config('retention.system_heartbeat_days', 30);

        if (Schema::hasTable('task_run_attempts')) {
            $counts['payload_snapshots_cleared'] = DB::table('task_run_attempts')
                ->where('created_at', '<', now()->subDays($snapshotDays))
                ->where(function ($q) {
                    $q->whereNotNull('request_body_redacted')
                        ->orWhereNotNull('response_body_truncated');
                })
                ->limit($batchSize)
                ->update([
                    'request_body_redacted' => null,
                    'response_body_truncated' => null,
                    'request_headers_redacted_json' => null,
                    'response_headers_json' => null,
                ]);
        }

        if (Schema::hasTable('idempotency_keys')) {
            $counts['idempotency_keys_deleted'] = DB::table('idempotency_keys')
                ->where('expires_at', '<', now())
                ->limit($batchSize)
                ->delete();
        }

        if (Schema::hasTable('password_reset_tokens')) {
            $counts['password_reset_tokens_deleted'] = DB::table('password_reset_tokens')
                ->where('created_at', '<', now()->subDay())
                ->delete();
        }

        if (Schema::hasTable('system_heartbeats')) {
            $counts['heartbeats_pruned'] = DB::table('system_heartbeats')
                ->where('last_seen_at', '<', now()->subDays($heartbeatDays))
                ->where('name', 'like', 'history:%')
                ->limit($batchSize)
                ->delete();
        }

        if (Schema::hasTable('tasks')) {
            $ttl = (int) config('scheduler.claim_ttl_minutes', 10);
            $counts['stale_claims_released'] = DB::table('tasks')
                ->whereNotNull('claim_token')
                ->where('claim_expires_at', '<', now())
                ->limit($batchSize)
                ->update([
                    'claim_token' => null,
                    'claimed_at' => null,
                    'claim_expires_at' => null,
                ]);
        }

        if (Schema::hasTable('task_run_attempts')) {
            DB::table('task_run_attempts')
                ->whereNotNull('claim_token')
                ->where('claim_expires_at', '<', now())
                ->where('attempt_state', 'running')
                ->limit($batchSize)
                ->update([
                    'attempt_state' => 'interrupted',
                    'claim_token' => null,
                    'claimed_at' => null,
                    'claim_expires_at' => null,
                    'finished_at' => now(),
                ]);
        }

        return $counts;
    }
}
