<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1 Extension R2: scope API idempotency keys to Environment (workspace).
 * Idempotent: safe if environment_id already exists.
 *
 * Existing rows are purged: they lack workspace scope and would silently
 * bypass replay after this change (keys are short-lived ≤24h anyway).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('idempotency_keys')) {
            return;
        }

        if (! Schema::hasColumn('idempotency_keys', 'environment_id')) {
            // Drop short-lived legacy records before adding workspace scope.
            DB::table('idempotency_keys')->delete();

            Schema::table('idempotency_keys', function (Blueprint $table) {
                $table->foreignId('environment_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('environments')
                    ->nullOnDelete();
            });
        } else {
            // Re-run / partial upgrade: remove any still-unscoped rows.
            DB::table('idempotency_keys')->whereNull('environment_id')->delete();
        }

        Schema::table('idempotency_keys', function (Blueprint $table) {
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
