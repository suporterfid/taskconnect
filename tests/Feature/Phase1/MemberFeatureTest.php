<?php

namespace Tests\Feature\Phase1;

use App\Domain\Shared\Enums\TenantRole;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class MemberFeatureTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_invite_creates_membership_for_existing_user(): void
    {
        [$admin, $tenant] = $this->createTenantAdmin();
        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Existing User',
        ]);

        $response = $this->actingAs($admin)->postJson(
            $this->tenantRoute($tenant, '/members'),
            [
                'email' => 'existing@example.com',
                'role' => TenantRole::TenantMember->value,
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.email', 'existing@example.com')
            ->assertJsonPath('data.name', 'Existing User')
            ->assertJsonPath('data.role', TenantRole::TenantMember->value);

        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $existing->id,
            'role' => TenantRole::TenantMember->value,
        ]);
    }

    public function test_invite_creates_user_and_membership_for_new_email(): void
    {
        Notification::fake();

        [$admin, $tenant] = $this->createTenantAdmin();

        $response = $this->actingAs($admin)->postJson(
            $this->tenantRoute($tenant, '/members'),
            [
                'email' => 'newbie@example.com',
                'name' => 'New Member',
                'role' => TenantRole::ReadOnlyViewer->value,
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.email', 'newbie@example.com')
            ->assertJsonPath('data.name', 'New Member')
            ->assertJsonPath('data.role', TenantRole::ReadOnlyViewer->value);

        $user = User::query()->where('email', 'newbie@example.com')->first();
        $this->assertNotNull($user);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_invite_rejects_duplicate_membership(): void
    {
        [$admin, $tenant] = $this->createTenantAdmin();

        $this->actingAs($admin)->postJson(
            $this->tenantRoute($tenant, '/members'),
            [
                'email' => $admin->email,
                'role' => TenantRole::TenantMember->value,
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_changes_role_and_rejects_demoting_last_admin(): void
    {
        [$admin, $tenant] = $this->createTenantAdmin();

        $memberUser = User::factory()->create();
        $membership = TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $memberUser->id,
            'role' => TenantRole::TenantMember,
        ]);

        $this->actingAs($admin)->patchJson(
            $this->tenantRoute($tenant, '/members/'.$membership->public_id),
            ['role' => TenantRole::ReadOnlyViewer->value],
        )->assertOk()
            ->assertJsonPath('data.role', TenantRole::ReadOnlyViewer->value);

        $adminMembership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $admin->id)
            ->firstOrFail();

        $this->actingAs($admin)->patchJson(
            $this->tenantRoute($tenant, '/members/'.$adminMembership->public_id),
            ['role' => TenantRole::TenantMember->value],
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_remove_works_and_rejects_self_and_last_admin(): void
    {
        [$admin, $tenant] = $this->createTenantAdmin();

        $memberUser = User::factory()->create();
        $membership = TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $memberUser->id,
            'role' => TenantRole::TenantMember,
        ]);

        $this->actingAs($admin)->deleteJson(
            $this->tenantRoute($tenant, '/members/'.$membership->public_id),
        )->assertNoContent();

        $this->assertDatabaseMissing('tenant_memberships', [
            'id' => $membership->id,
        ]);

        $adminMembership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $admin->id)
            ->firstOrFail();

        $this->actingAs($admin)->deleteJson(
            $this->tenantRoute($tenant, '/members/'.$adminMembership->public_id),
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['member']);

        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)->deleteJson(
            $this->tenantRoute($tenant, '/members/'.$adminMembership->public_id),
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['member']);
    }

    public function test_non_admin_can_list_but_cannot_write(): void
    {
        [$admin, $tenant] = $this->createTenantAdmin();
        [$member] = $this->createTenantAdmin('Other', TenantRole::TenantMember);

        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'role' => TenantRole::TenantMember,
        ]);

        $this->actingAs($member)->getJson($this->tenantRoute($tenant, '/members'))
            ->assertOk();

        $this->actingAs($member)->postJson(
            $this->tenantRoute($tenant, '/members'),
            [
                'email' => 'blocked@example.com',
                'role' => TenantRole::TenantMember->value,
            ],
        )->assertForbidden();
    }
}
