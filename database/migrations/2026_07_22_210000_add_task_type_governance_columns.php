<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1 Extension R4: task type governance columns on tasks.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'task_type')) {
                $table->string('task_type', 64)->nullable()->after('definition_status');
                $table->index(['task_type', 'definition_status', 'next_run_at'], 'tasks_type_status_next_run_idx');
            }
            if (! Schema::hasColumn('tasks', 'priority')) {
                $table->unsignedInteger('priority')->default(0)->after('task_type');
            }
            if (! Schema::hasColumn('tasks', 'weight')) {
                $table->unsignedInteger('weight')->nullable()->after('priority');
            }
            if (! Schema::hasColumn('tasks', 'timeout_ms')) {
                $table->unsignedInteger('timeout_ms')->nullable()->after('weight');
            }
            if (! Schema::hasColumn('tasks', 'egress_profile')) {
                $table->string('egress_profile', 64)->nullable()->after('timeout_ms');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            foreach (['egress_profile', 'timeout_ms', 'weight', 'priority', 'task_type'] as $column) {
                if (Schema::hasColumn('tasks', $column)) {
                    if ($column === 'task_type') {
                        try {
                            $table->dropIndex('tasks_type_status_next_run_idx');
                        } catch (\Throwable) {
                            // ignore
                        }
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};
