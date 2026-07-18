<?php

namespace App\Application\Secrets;

use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Secret;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final class SecretService
{
    public function create(
        Tenant $tenant,
        Environment $environment,
        User $actor,
        string $name,
        string $plaintext,
    ): Secret {
        return Secret::query()->create([
            'tenant_id' => $tenant->id,
            'environment_id' => $environment->id,
            'name' => $name,
            'encrypted_payload' => Crypt::encryptString($plaintext),
            'version' => 1,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    public function rotate(Secret $secret, User $actor, string $plaintext): Secret
    {
        $secret->fill([
            'encrypted_payload' => Crypt::encryptString($plaintext),
            'version' => $secret->version + 1,
            'updated_by' => $actor->id,
        ]);
        $secret->save();

        return $secret->fresh();
    }

    public function archive(Secret $secret, User $actor): Secret
    {
        $secret->updated_by = $actor->id;
        $secret->save();
        $secret->archive();

        return $secret->fresh();
    }

    public function decrypt(Secret $secret): string
    {
        try {
            return Crypt::decryptString($secret->encrypted_payload);
        } catch (DecryptException $exception) {
            throw new RuntimeException('Unable to decrypt secret payload.', 0, $exception);
        }
    }
}
