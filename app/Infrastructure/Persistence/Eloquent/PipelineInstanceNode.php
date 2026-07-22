<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Pipelines\PipelineNodeStatus;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineInstanceNode extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'pipeline_instance_id',
        'node_key',
        'task_type',
        'status',
        'depends_on_json',
        'on_success',
        'on_failure',
        'task_id',
        'task_run_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PipelineNodeStatus::class,
            'depends_on_json' => 'array',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'pnode';
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(PipelineInstance::class, 'pipeline_instance_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function taskRun(): BelongsTo
    {
        return $this->belongsTo(TaskRun::class, 'task_run_id');
    }

    /**
     * @return list<string>
     */
    public function dependsOn(): array
    {
        $deps = $this->depends_on_json ?? [];

        return is_array($deps) ? array_values(array_filter($deps, 'is_string')) : [];
    }
}
