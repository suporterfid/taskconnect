<?php

namespace Tests\Feature\Phase1;

use App\Application\Audit\AuditLogger;
use App\Domain\Shared\Enums\TenantRole;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use App\Infrastructure\Persistence\Eloquent\TenantMembership;
use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PreferencesAndAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_preferences(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);

        $this->actingAs($user);

        $response = $this->patchJson('/api/v1/me/preferences', [
            'locale' => 'pt-BR',
            'timezone' => 'America/Sao_Paulo',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.preferences.locale', 'pt-BR')
            ->assertJsonPath('data.preferences.timezone', 'America/Sao_Paulo');

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'locale' => 'pt-BR',
            'timezone' => 'America/Sao_Paulo',
        ]);
    }

    public function test_tenant_member_can_list_audit_logs(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        Environment::factory()->create(['tenant_id' => $tenant->id]);
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => TenantRole::TenantMember,
        ]);

        app(AuditLogger::class)->log(
            action: 'task.created',
            resourceType: 'task',
            resourceId: 'tsk_test',
            tenantId: $tenant->id,
            summary: ['name' => 'Demo'],
            actor: $user,
        );

        $this->actingAs($user);

        $this->getJson("/api/v1/tenants/{$tenant->public_id}/audit-logs")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'task.created')
            ->assertJsonPath('data.0.resource_id', 'tsk_test');
    }

    public function test_outsider_cannot_list_audit_logs(): void
    {
        $outsider = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($outsider);

        $this->getJson("/api/v1/tenants/{$tenant->public_id}/audit-logs")
            ->assertNotFound();
    }
}
