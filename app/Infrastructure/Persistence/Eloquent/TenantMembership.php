<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Shared\Enums\TenantRole;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use Database\Factories\TenantMembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMembership extends Model
{
    /** @use HasFactory<TenantMembershipFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'user_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'mem';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): TenantMembershipFactory
    {
        return TenantMembershipFactory::new();
    }
}
