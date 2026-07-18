<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Execution\Enums\RunState;
use App\Domain\Execution\Enums\TriggerType;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskRun extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'environment_id',
        'task_id',
        'trigger_type',
        'scheduled_for',
        'occurrence_key',
        'idempotency_key',
        'run_state',
        'attempt_count',
        'next_attempt_at',
        'started_at',
        'finished_at',
        'final_http_status',
        'final_error_code',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => TriggerType::class,
            'run_state' => RunState::class,
            'scheduled_for' => 'datetime',
            'next_attempt_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'run';
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TaskRunAttempt::class)->orderBy('attempt_number');
    }

    public function latestAttempt(): ?TaskRunAttempt
    {
        return $this->attempts()->orderByDesc('attempt_number')->first();
    }
}
