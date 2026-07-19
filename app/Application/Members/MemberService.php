<?php

namespace App\Application\Members;

use App\Domain\Shared\Enums\TenantRole;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MemberService
{
    /**
     * @return array{membership: TenantMembership, created_user: bool}
     */
    public function invite(
        Tenant $tenant,
        string $email,
        TenantRole $role,
        ?string $name = null,
    ): array {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->first();
        $createdUser = false;

        if ($user === null) {
            $resolvedName = filled($name) ? trim($name) : Str::before($email, '@');

            $user = User::query()->create([
                'name' => $resolvedName !== '' ? $resolvedName : $email,
                'email' => $email,
                'password' => Str::password(32),
            ]);
            $createdUser = true;
        }

        if (
            TenantMembership::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'email' => ['This user is already a member of this tenant.'],
            ]);
        }

        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        if ($createdUser) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return [
            'membership' => $membership->load('user'),
            'created_user' => $createdUser,
        ];
    }

    public function updateRole(
        Tenant $tenant,
        TenantMembership $membership,
        TenantRole $role,
    ): TenantMembership {
        $this->assertMembershipBelongsToTenant($membership, $tenant);

        if (
            $membership->role === TenantRole::TenantAdmin
            && $role !== TenantRole::TenantAdmin
            && $this->isLastTenantAdmin($tenant, $membership)
        ) {
            throw ValidationException::withMessages([
                'role' => ['Cannot demote the last tenant admin.'],
            ]);
        }

        $membership->role = $role;
        $membership->save();

        return $membership->fresh(['user']);
    }

    public function remove(
        Tenant $tenant,
        TenantMembership $membership,
        User $actor,
    ): void {
        $this->assertMembershipBelongsToTenant($membership, $tenant);

        if ($membership->user_id === $actor->id) {
            throw ValidationException::withMessages([
                'member' => ['You cannot remove yourself from the tenant.'],
            ]);
        }

        if (
            $membership->role === TenantRole::TenantAdmin
            && $this->isLastTenantAdmin($tenant, $membership)
        ) {
            throw ValidationException::withMessages([
                'member' => ['Cannot remove the last tenant admin.'],
            ]);
        }

        $membership->delete();
    }

    private function assertMembershipBelongsToTenant(TenantMembership $membership, Tenant $tenant): void
    {
        if ($membership->tenant_id !== $tenant->id) {
            abort(404);
        }
    }

    private function isLastTenantAdmin(Tenant $tenant, TenantMembership $membership): bool
    {
        $adminCount = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', TenantRole::TenantAdmin->value)
            ->count();

        return $adminCount <= 1 && $membership->role === TenantRole::TenantAdmin;
    }
}
