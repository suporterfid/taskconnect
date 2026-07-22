<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1 Extension R2: scope API idempotency keys to Environment (workspace).
 * Idempotent: safe if environment_id already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('idempotency_keys')) {
            return;
        }

        if (! Schema::hasColumn('idempotency_keys', 'environment_id')) {
            Schema::table('idempotency_keys', function (Blueprint $table) {
                $table->foreignId('environment_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('environments')
                    ->nullOnDelete();
            });
        }

        Schema::table('idempotency_keys', function (Blueprint $table) {
            // Replace tenant+key+route uniqueness with workspace-aware uniqueness.
            try {
                $table->dropUnique(['tenant_id', 'key', 'route']);
            } catch (\Throwable) {
                // Index name may differ across drivers / prior runs.
            }

            try {
                $table->unique(
                    ['tenant_id', 'environment_id', 'key', 'route'],
                    'idempotency_keys_tenant_env_key_route_unique',
                );
            } catch (\Throwable) {
                // Already present.
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('idempotency_keys') || ! Schema::hasColumn('idempotency_keys', 'environment_id')) {
            return;
        }

        Schema::table('idempotency_keys', function (Blueprint $table) {
            try {
                $table->dropUnique('idempotency_keys_tenant_env_key_route_unique');
            } catch (\Throwable) {
                // ignore
            }

            try {
                $table->unique(['tenant_id', 'key', 'route']);
            } catch (\Throwable) {
                // ignore
            }

            $table->dropConstrainedForeignId('environment_id');
        });
    }
};
