<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('coalesce_key', 255)->nullable()->after('egress_profile');
            $table->index(
                ['tenant_id', 'environment_id', 'coalesce_key', 'created_at'],
                'tasks_coalesce_workspace_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_coalesce_workspace_idx');
            $table->dropColumn('coalesce_key');
        });
    }
};
