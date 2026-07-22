<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1 Extension R1: attach Environment (workspace alias) to audit_logs.
 * Idempotent: safe if environment_id already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        if (! Schema::hasColumn('audit_logs', 'environment_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->foreignId('environment_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('environments')
                    ->nullOnDelete();

                $table->index(
                    ['tenant_id', 'environment_id', 'created_at'],
                    'audit_logs_tenant_env_created_idx',
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs') || ! Schema::hasColumn('audit_logs', 'environment_id')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_tenant_env_created_idx');
            $table->dropConstrainedForeignId('environment_id');
        });
    }
};
