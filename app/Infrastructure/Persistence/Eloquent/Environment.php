<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use App\Infrastructure\Persistence\Eloquent\Concerns\SoftArchivable;
use Database\Factories\EnvironmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Environment extends Model
{
    /** @use HasFactory<EnvironmentFactory> */
    use HasFactory, HasPublicId, SoftArchivable;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'name',
        'slug',
        'dead_run_email_enabled',
        'dead_run_webhook_enabled',
        'dead_run_webhook_url',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'dead_run_email_enabled' => 'boolean',
            'dead_run_webhook_enabled' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'env';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected static function newFactory(): EnvironmentFactory
    {
        return EnvironmentFactory::new();
    }
}
