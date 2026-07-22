<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Execution\Enums\TaskDefinitionStatus;
use App\Domain\Execution\RetryPolicy;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use App\Infrastructure\Persistence\Eloquent\Concerns\SoftArchivable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasPublicId, SoftArchivable;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'environment_id',
        'endpoint_profile_id',
        'name',
        'description',
        'definition_status',
        'task_type',
        'priority',
        'weight',
        'timeout_ms',
        'egress_profile',
        'coalesce_key',
        'method',
        'url_or_path',
        'headers_json',
        'query_json',
        'body_template',
        'content_type',
        'timezone',
        'retry_policy_json',
        'next_run_at',
        'last_run_at',
        'last_run_state',
        'claim_token',
        'claimed_at',
        'claim_expires_at',
        'created_by',
        'updated_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'definition_status' => TaskDefinitionStatus::class,
            'priority' => 'integer',
            'weight' => 'integer',
            'timeout_ms' => 'integer',
            'headers_json' => 'array',
            'query_json' => 'array',
            'retry_policy_json' => 'array',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'claimed_at' => 'datetime',
            'claim_expires_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'task';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function endpointProfile(): BelongsTo
    {
        return $this->belongsTo(EndpointProfile::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(TaskSchedule::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TaskRun::class);
    }

    public function retryPolicy(): RetryPolicy
    {
        return RetryPolicy::fromArray($this->retry_policy_json);
    }

    public function hasActiveClaim(\DateTimeImmutable $now): bool
    {
        return $this->claim_token !== null
            && $this->claim_expires_at !== null
            && $this->claim_expires_at->greaterThan($now);
    }

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }
}
