<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetentionSettingsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->user() === null) {
            abort(401);
        }

        return response()->json([
            'data' => [
                'payload_snapshots_days' => (int) config('retention.payload_snapshots_days'),
                'attempt_metadata_days' => (int) config('retention.attempt_metadata_days'),
                'run_summary_days' => (int) config('retention.run_summary_days'),
                'audit_logs_days' => (int) config('retention.audit_logs_days'),
                'api_idempotency_hours' => (int) config('retention.api_idempotency_hours'),
                'system_heartbeat_days' => (int) config('retention.system_heartbeat_days'),
            ],
        ]);
    }
}
