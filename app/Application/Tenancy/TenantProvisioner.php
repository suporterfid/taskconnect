<?php

namespace App\Application\Tenancy;

use App\Domain\Shared\Enums\TenantRole;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Support\Str;

class TenantProvisioner
{
    /** @var list<array{name: string, slug: string}> */
    private const DEFAULT_ENVIRONMENTS = [
        ['name' => 'Development', 'slug' => 'development'],
        ['name' => 'Staging', 'slug' => 'staging'],
        ['name' => 'Production', 'slug' => 'production'],
    ];

    public function create(string $name, ?User $owner = null, ?string $slug = null): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => $name,
            'slug' => $slug ?? $this->uniqueSlug($name),
        ]);

        foreach (self::DEFAULT_ENVIRONMENTS as $environment) {
            Environment::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $environment['name'],
                'slug' => $environment['slug'],
            ]);
        }

        if ($owner !== null && ! $owner->isPlatformAdmin()) {
            TenantMembership::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
                'role' => TenantRole::TenantAdmin,
            ]);
        }

        return $tenant->fresh(['environments']);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'tenant';
        }

        $slug = $base;
        $suffix = 1;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
