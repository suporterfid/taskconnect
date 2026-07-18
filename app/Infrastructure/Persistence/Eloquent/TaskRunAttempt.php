<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Execution\Enums\AttemptState;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskRunAttempt extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'environment_id',
        'task_run_id',
        'attempt_number',
        'attempt_state',
        'claim_token',
        'claimed_at',
        'claim_expires_at',
        'started_at',
        'finished_at',
        'duration_ms',
        'request_url_redacted',
        'request_headers_redacted_json',
        'request_body_redacted',
        'response_status',
        'response_headers_json',
        'response_body_truncated',
        'response_body_sha256',
        'transport_error_code',
        'transport_error_message',
        'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_state' => AttemptState::class,
            'claimed_at' => 'datetime',
            'claim_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'request_headers_redacted_json' => 'array',
            'response_headers_json' => 'array',
            'next_retry_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'att';
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TaskRun::class, 'task_run_id');
    }

    public function hasActiveClaim(\DateTimeImmutable $now): bool
    {
        return $this->claim_token !== null
            && $this->claim_expires_at !== null
            && $this->claim_expires_at->greaterThan($now);
    }
}
