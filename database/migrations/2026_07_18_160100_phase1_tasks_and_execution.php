<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('endpoint_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('definition_status');
            $table->string('method')->default('POST');
            $table->string('url_or_path');
            $table->json('headers_json')->nullable();
            $table->json('query_json')->nullable();
            $table->text('body_template')->nullable();
            $table->string('content_type')->nullable();
            $table->string('timezone')->default('UTC');
            $table->json('retry_policy_json')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_state')->nullable();
            $table->string('claim_token')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('claim_expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();

            $table->index(['tenant_id', 'definition_status', 'next_run_at'], 'tasks_tenant_status_next_idx');
            $table->index(['claim_expires_at'], 'tasks_claim_expires_idx');
            });
        }

        if (! Schema::hasTable('task_schedules')) {
            Schema::create('task_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('schedule_kind');
            $table->json('schedule_config_json');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('task_runs')) {
            Schema::create('task_runs', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('trigger_type');
            $table->timestamp('scheduled_for')->nullable();
            $table->string('occurrence_key');
            $table->string('idempotency_key');
            $table->string('run_state');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedSmallInteger('final_http_status')->nullable();
            $table->string('final_error_code')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'occurrence_key'], 'task_runs_task_occurrence_unique');
            $table->index(['tenant_id', 'environment_id', 'run_state', 'next_attempt_at'], 'task_runs_tenant_env_state_next_idx');
            $table->index(['tenant_id', 'task_id', 'created_at'], 'task_runs_tenant_task_created_idx');
            });
        }

        if (! Schema::hasTable('task_run_attempts')) {
            Schema::create('task_run_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('attempt_state');
            $table->string('claim_token')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('claim_expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('request_url_redacted')->nullable();
            $table->json('request_headers_redacted_json')->nullable();
            $table->text('request_body_redacted')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_headers_json')->nullable();
            $table->text('response_body_truncated')->nullable();
            $table->string('response_body_sha256')->nullable();
            $table->string('transport_error_code')->nullable();
            $table->string('transport_error_message')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->unique(['task_run_id', 'attempt_number'], 'task_run_attempts_run_number_unique');
            $table->index(['claim_expires_at'], 'task_run_attempts_claim_expires_idx');
            });
        }

        if (! Schema::hasTable('system_heartbeats')) {
            Schema::create('system_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamp('last_seen_at');
            $table->json('meta_json')->nullable();
            $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_heartbeats');
        Schema::dropIfExists('task_run_attempts');
        Schema::dropIfExists('task_runs');
        Schema::dropIfExists('task_schedules');
        Schema::dropIfExists('tasks');

    }
};
