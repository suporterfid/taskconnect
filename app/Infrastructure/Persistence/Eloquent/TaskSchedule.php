<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Scheduling\ScheduleConfig;
use App\Domain\Scheduling\ScheduleKind;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskSchedule extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'task_id',
        'schedule_kind',
        'schedule_config_json',
        'starts_at',
        'ends_at',
        'last_calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'schedule_kind' => ScheduleKind::class,
            'schedule_config_json' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_calculated_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'tsc';
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function toScheduleConfig(): ScheduleConfig
    {
        return ScheduleConfig::fromArray($this->schedule_config_json);
    }
}
