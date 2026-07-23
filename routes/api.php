<?php

use App\Http\Controllers\Api\V1\ApiKeyController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DlqController;
use App\Http\Controllers\Api\V1\EndpointProfileController;
use App\Http\Controllers\Api\V1\EnvironmentController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\PipelineInstanceController;
use App\Http\Controllers\Api\V1\PlatformHealthController;
use App\Http\Controllers\Api\V1\PlatformMetricsController;
use App\Http\Controllers\Api\V1\RetentionSettingsController;
use App\Http\Controllers\Api\V1\SchedulePreviewController;
use App\Http\Controllers\Api\V1\SecretController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskRunController;
use App\Http\Controllers\Api\V1\Testing\E2eFixtureController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\UserPreferencesController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('auth/login', LoginController::class);
        Route::post('auth/forgot-password', ForgotPasswordController::class);
        Route::post('auth/reset-password', ResetPasswordController::class);
    });

    Route::middleware('auth.api_or_sanctum')->group(function (): void {
        Route::post('auth/logout', LogoutController::class);
        Route::get('me', MeController::class);
        Route::patch('me/preferences', [UserPreferencesController::class, 'update']);
        Route::get('platform/health', PlatformHealthController::class);
        Route::get('platform/metrics', PlatformMetricsController::class);
        Route::get('platform/retention', RetentionSettingsController::class);

        Route::get('tenants', [TenantController::class, 'index']);
        Route::post('tenants', [TenantController::class, 'store']);

        Route::middleware(['tenant.context', 'grandpasson.workspace'])->group(function (): void {
            Route::get('tenants/{tenantId}', [TenantController::class, 'show']);
            Route::patch('tenants/{tenantId}', [TenantController::class, 'update']);

            Route::get('tenants/{tenantId}/environments', [EnvironmentController::class, 'index']);
            Route::post('tenants/{tenantId}/environments', [EnvironmentController::class, 'store']);

            Route::patch('tenants/{tenantId}/environments/{environmentId}', [EnvironmentController::class, 'update']);
            Route::delete('tenants/{tenantId}/environments/{environmentId}', [EnvironmentController::class, 'destroy']);

            Route::get('tenants/{tenantId}/api-keys', [ApiKeyController::class, 'index']);
            Route::post('tenants/{tenantId}/api-keys', [ApiKeyController::class, 'store']);
            Route::patch('tenants/{tenantId}/api-keys/{apiKeyId}', [ApiKeyController::class, 'update']);
            Route::delete('tenants/{tenantId}/api-keys/{apiKeyId}', [ApiKeyController::class, 'destroy']);

            Route::get('tenants/{tenantId}/members', [MemberController::class, 'index']);
            Route::post('tenants/{tenantId}/members', [MemberController::class, 'store']);
            Route::patch('tenants/{tenantId}/members/{memberId}', [MemberController::class, 'update']);
            Route::delete('tenants/{tenantId}/members/{memberId}', [MemberController::class, 'destroy']);

            Route::get('tenants/{tenantId}/audit-logs', [AuditLogController::class, 'index']);

            Route::get('tenants/{tenantId}/environments/{environmentId}/dashboard', DashboardController::class);
            Route::post('tenants/{tenantId}/environments/{environmentId}/schedules/preview', SchedulePreviewController::class);

            Route::get('tenants/{tenantId}/environments/{environmentId}/secrets', [SecretController::class, 'index']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/secrets', [SecretController::class, 'store']);
            Route::get('tenants/{tenantId}/environments/{environmentId}/secrets/{secretId}', [SecretController::class, 'show']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/secrets/{secretId}/rotate', [SecretController::class, 'rotate']);
            Route::delete('tenants/{tenantId}/environments/{environmentId}/secrets/{secretId}', [SecretController::class, 'destroy']);

            Route::get('tenants/{tenantId}/environments/{environmentId}/endpoint-profiles', [EndpointProfileController::class, 'index']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/endpoint-profiles', [EndpointProfileController::class, 'store']);
            Route::get('tenants/{tenantId}/environments/{environmentId}/endpoint-profiles/{profileId}', [EndpointProfileController::class, 'show']);
            Route::patch('tenants/{tenantId}/environments/{environmentId}/endpoint-profiles/{profileId}', [EndpointProfileController::class, 'update']);
            Route::delete('tenants/{tenantId}/environments/{environmentId}/endpoint-profiles/{profileId}', [EndpointProfileController::class, 'destroy']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/endpoint-profiles/{profileId}/test', [EndpointProfileController::class, 'test']);

            Route::get('tenants/{tenantId}/environments/{environmentId}/pipelines', [PipelineInstanceController::class, 'templates']);
            Route::get('tenants/{tenantId}/environments/{environmentId}/pipeline-instances', [PipelineInstanceController::class, 'index']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/pipelines/{templateName}/instances', [PipelineInstanceController::class, 'store'])
                ->middleware(['submit.throttle', 'idempotency']);
            Route::get('tenants/{tenantId}/environments/{environmentId}/pipelines/{templateName}/instances/{instanceId}', [PipelineInstanceController::class, 'show']);

            Route::get('tenants/{tenantId}/environments/{environmentId}/dlq', [DlqController::class, 'index']);
            Route::get('tenants/{tenantId}/environments/{environmentId}/dlq/{runId}', [DlqController::class, 'show']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/dlq/{runId}/replay', [DlqController::class, 'replay']);

            // Playwright E2E fixture seeding only (issue #79) — 404s outside local/testing.
            Route::post('tenants/{tenantId}/environments/{environmentId}/e2e/dlq-fixture', [E2eFixtureController::class, 'seedDeadRun'])
                ->middleware('e2e.testing.only');

            Route::get('tenants/{tenantId}/environments/{environmentId}/tasks', [TaskController::class, 'index']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/tasks', [TaskController::class, 'store'])
                ->middleware(['submit.throttle', 'idempotency']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/tasks/bulk-pause', [TaskController::class, 'bulkPause']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/tasks/bulk-resume', [TaskController::class, 'bulkResume']);
            Route::get('tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}', [TaskController::class, 'show']);
            Route::patch('tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}', [TaskController::class, 'update']);
            Route::delete('tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}', [TaskController::class, 'destroy']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}/activate', [TaskController::class, 'activate']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}/pause', [TaskController::class, 'pause']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}/resume', [TaskController::class, 'resume']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}/run-now', [TaskController::class, 'runNow'])
                ->middleware(['submit.throttle', 'idempotency']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}/test', [TaskController::class, 'test'])
                ->middleware('submit.throttle');
            Route::post('tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}/duplicate', [TaskController::class, 'duplicate']);


            Route::get('tenants/{tenantId}/environments/{environmentId}/task-runs', [TaskRunController::class, 'index']);
            Route::get('tenants/{tenantId}/environments/{environmentId}/task-runs/{runId}', [TaskRunController::class, 'show']);
            Route::get('tenants/{tenantId}/environments/{environmentId}/task-runs/{runId}/attempts', [TaskRunController::class, 'attempts']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/task-runs/{runId}/cancel', [TaskRunController::class, 'cancel']);
            Route::post('tenants/{tenantId}/environments/{environmentId}/task-runs/{runId}/retry', [TaskRunController::class, 'retry']);
        });
    });
});
