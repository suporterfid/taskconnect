<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_limit_buckets', function (Blueprint $table): void {
            $table->id();
            $table->string('bucket_key', 191)->unique();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('resets_at');
            $table->timestamps();

            $table->index('resets_at');
        });

        Schema::table('environments', function (Blueprint $table): void {
            $table->unsignedInteger('submit_rate_limit_per_minute')->nullable()->after('dead_run_webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('environments', function (Blueprint $table): void {
            $table->dropColumn('submit_rate_limit_per_minute');
        });

        Schema::dropIfExists('rate_limit_buckets');
    }
};
