<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Pipelines\PipelineInstanceStatus;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineInstance extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'environment_id',
        'template_name',
        'status',
        'input_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PipelineInstanceStatus::class,
            'input_json' => 'array',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'pipe';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(PipelineInstanceNode::class)->orderBy('id');
    }
}
