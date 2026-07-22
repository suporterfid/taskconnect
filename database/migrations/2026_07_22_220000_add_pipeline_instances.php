<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_instances', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 64)->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('environment_id')->constrained('environments')->cascadeOnDelete();
            $table->string('template_name', 128);
            $table->string('status', 32);
            $table->json('input_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'environment_id', 'created_at'], 'pipeline_instances_workspace_created_idx');
            $table->index(['template_name', 'status'], 'pipeline_instances_template_status_idx');
        });

        Schema::create('pipeline_instance_nodes', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 64)->unique();
            $table->foreignId('pipeline_instance_id')->constrained('pipeline_instances')->cascadeOnDelete();
            $table->string('node_key', 128);
            $table->string('task_type', 128);
            $table->string('status', 32);
            $table->json('depends_on_json')->nullable();
            $table->string('on_success', 128)->nullable();
            $table->string('on_failure', 128)->nullable();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('task_run_id')->nullable()->constrained('task_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pipeline_instance_id', 'node_key'], 'pipeline_nodes_instance_key_uq');
            $table->index(['status', 'task_run_id'], 'pipeline_nodes_status_run_idx');
        });

        Schema::table('task_runs', function (Blueprint $table): void {
            $table->foreignId('pipeline_instance_id')->nullable()->after('task_id')->constrained('pipeline_instances')->nullOnDelete();
            $table->foreignId('pipeline_node_id')->nullable()->after('pipeline_instance_id')->constrained('pipeline_instance_nodes')->nullOnDelete();
            $table->index(['pipeline_instance_id', 'pipeline_node_id'], 'task_runs_pipeline_idx');
        });
    }

    public function down(): void
    {
        Schema::table('task_runs', function (Blueprint $table): void {
            $table->dropIndex('task_runs_pipeline_idx');
            $table->dropConstrainedForeignId('pipeline_node_id');
            $table->dropConstrainedForeignId('pipeline_instance_id');
        });

        Schema::dropIfExists('pipeline_instance_nodes');
        Schema::dropIfExists('pipeline_instances');
    }
};
