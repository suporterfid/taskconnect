<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\EndpointProfiles\AuthMode;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasPublicId;
use App\Infrastructure\Persistence\Eloquent\Concerns\SoftArchivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndpointProfile extends Model
{
    use HasPublicId, SoftArchivable;

    public const AUTH_CONFIG_KEY = '__auth_config';

    protected $fillable = [
        'public_id',
        'tenant_id',
        'environment_id',
        'name',
        'description',
        'base_url',
        'method',
        'headers_json',
        'auth_mode',
        'secret_id',
        'connect_timeout',
        'total_timeout',
        'follow_redirects',
        'verify_tls',
        'allowed_path_prefix',
        'enabled',
        'created_by',
        'updated_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'headers_json' => 'array',
            'auth_mode' => AuthMode::class,
            'follow_redirects' => 'boolean',
            'verify_tls' => 'boolean',
            'enabled' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'ep';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function secret(): BelongsTo
    {
        return $this->belongsTo(Secret::class);
    }

    /**
     * @return array<string, string>
     */
    public function visibleHeaders(): array
    {
        $headers = $this->headers_json ?? [];
        unset($headers[self::AUTH_CONFIG_KEY]);

        /** @var array<string, string> $normalized */
        $normalized = [];

        foreach ($headers as $key => $value) {
            $normalized[(string) $key] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    public function authConfig(): array
    {
        $config = $this->headers_json[self::AUTH_CONFIG_KEY] ?? [];

        return is_array($config) ? $config : [];
    }
}
