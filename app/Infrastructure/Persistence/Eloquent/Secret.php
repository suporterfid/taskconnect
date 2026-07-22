<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use App\Infrastructure\Persistence\Eloquent\Concerns\SoftArchivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Secret extends Model
{
    use HasPublicId, SoftArchivable;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'environment_id',
        'name',
        'encrypted_payload',
        'version',
        'created_by',
        'updated_by',
        'archived_at',
    ];

    protected $hidden = [
        'encrypted_payload',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'sec';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function endpointProfiles(): HasMany
    {
        return $this->hasMany(EndpointProfile::class);
    }
}
