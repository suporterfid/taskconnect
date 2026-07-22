<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('environments', function (Blueprint $table): void {
            $table->boolean('dead_run_email_enabled')->default(true)->after('slug');
            $table->boolean('dead_run_webhook_enabled')->default(false)->after('dead_run_email_enabled');
            $table->string('dead_run_webhook_url', 2048)->nullable()->after('dead_run_webhook_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('environments', function (Blueprint $table): void {
            $table->dropColumn([
                'dead_run_email_enabled',
                'dead_run_webhook_enabled',
                'dead_run_webhook_url',
            ]);
        });
    }
};
