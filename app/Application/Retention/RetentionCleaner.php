<?php

namespace App\Application\Retention;

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
            'attempt_metadata_cleared' => 0,
            'run_summaries_deleted' => 0,
            'audit_logs_deleted' => 0,
            'idempotency_keys_deleted' => 0,
            'password_reset_tokens_deleted' => 0,
            'heartbeats_pruned' => 0,
            'stale_claims_released' => 0,
        ];

        $snapshotDays = (int) config('retention.payload_snapshots_days', 30);
        $metadataDays = (int) config('retention.attempt_metadata_days', 180);
        $runSummaryDays = (int) config('retention.run_summary_days', 365);
        $auditLogsDays = (int) config('retention.audit_logs_days', 365);
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

            $counts['attempt_metadata_cleared'] = DB::table('task_run_attempts')
                ->where('created_at', '<', now()->subDays($metadataDays))
                ->where(function ($q) {
                    $q->whereNotNull('transport_error_message')
                        ->orWhereNotNull('transport_error_code')
                        ->orWhereNotNull('request_url_redacted')
                        ->orWhereNotNull('request_body_redacted')
                        ->orWhereNotNull('response_body_truncated')
                        ->orWhereNotNull('request_headers_redacted_json')
                        ->orWhereNotNull('response_headers_json')
                        ->orWhereNotNull('response_body_sha256');
                })
                ->limit($batchSize)
                ->update([
                    'transport_error_message' => null,
                    'transport_error_code' => null,
                    'request_url_redacted' => null,
                    'request_body_redacted' => null,
                    'response_body_truncated' => null,
                    'request_headers_redacted_json' => null,
                    'response_headers_json' => null,
                    'response_body_sha256' => null,
                ]);
        }

        if (Schema::hasTable('task_runs')) {
            $runIds = DB::table('task_runs')
                ->whereIn('run_state', ['succeeded', 'dead', 'cancelled', 'blocked'])
                ->where(function ($q) use ($runSummaryDays) {
                    $q->where('finished_at', '<', now()->subDays($runSummaryDays))
                        ->orWhere(function ($inner) use ($runSummaryDays) {
                            $inner->whereNull('finished_at')
                                ->where('created_at', '<', now()->subDays($runSummaryDays));
                        });
                })
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($runIds->isNotEmpty()) {
                if (Schema::hasTable('task_run_attempts')) {
                    DB::table('task_run_attempts')->whereIn('task_run_id', $runIds)->delete();
                }

                $counts['run_summaries_deleted'] = DB::table('task_runs')->whereIn('id', $runIds)->delete();
            }
        }

        if (Schema::hasTable('audit_logs')) {
            $counts['audit_logs_deleted'] = DB::table('audit_logs')
                ->where('created_at', '<', now()->subDays($auditLogsDays))
                ->limit($batchSize)
                ->delete();
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

        return $counts;
    }
}
