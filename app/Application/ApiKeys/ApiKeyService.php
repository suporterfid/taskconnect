<?php

namespace App\Application\ApiKeys;

use App\Infrastructure\Persistence\Eloquent\ApiKey;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Support\Str;

final class ApiKeyService
{
    private const KEY_PREFIX = 'tc_';

    /**
     * @param  list<string>  $permissions
     * @return array{api_key: ApiKey, plaintext: string}
     */
    public function create(
        Tenant $tenant,
        User $actor,
        string $name,
        array $permissions,
        ?Environment $environment = null,
        ?\DateTimeInterface $expiresAt = null,
    ): array {
        $plaintext = self::KEY_PREFIX.Str::random(40);
        $prefix = substr($plaintext, 0, 8);

        $apiKey = ApiKey::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment?->id,
            'name' => $name,
            'key_prefix' => $prefix,
            'key_hash' => $this->hashKey($plaintext),
            'permissions' => $permissions,
            'created_by' => $actor->id,
            'expires_at' => $expiresAt,
        ]);

        return [
            'api_key' => $apiKey,
            'plaintext' => $plaintext,
        ];
    }

    public function revoke(ApiKey $apiKey): ApiKey
    {
        $apiKey->revoked_at = now();
        $apiKey->save();

        return $apiKey->fresh();
    }

    public function authenticate(string $plaintext): ?ApiKey
    {
        if (! str_starts_with($plaintext, self::KEY_PREFIX)) {
            return null;
        }

        $prefix = substr($plaintext, 0, 8);

        $candidates = ApiKey::query()
            ->where('key_prefix', $prefix)
            ->whereNull('revoked_at')
            ->get();

        foreach ($candidates as $candidate) {
            if (! hash_equals($candidate->key_hash, $this->hashKey($plaintext))) {
                continue;
            }

            if (! $candidate->isActive()) {
                return null;
            }

            $candidate->forceFill(['last_used_at' => now()])->save();

            return $candidate->fresh();
        }

        return null;
    }

    public function hashKey(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }
}
