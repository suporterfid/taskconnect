<?php

namespace Tests\Feature\Phase1;

use App\Infrastructure\Persistence\Eloquent\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantFixtures;
use Tests\TestCase;

class WorkspaceScopingTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    public function test_task_responses_include_workspace_id_alias(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $create = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            [
                'name' => 'Workspace Task',
                'method' => 'POST',
                'url_or_path' => 'https://receiver:8080/hook',
                'workspace_id' => $environment->public_id,
                'schedule' => [
                    'kind' => 'daily_at',
                    'timezone' => 'UTC',
                    'time' => '09:00',
                ],
            ],
        );

        $create->assertCreated()
            ->assertJsonPath('data.workspace_id', $environment->public_id);

        $this->actingAs($admin)->getJson(
            $this->environmentRoute($tenant, $environment, '/tasks/'.$create->json('data.id')),
        )
            ->assertOk()
            ->assertJsonPath('data.workspace_id', $environment->public_id);
    }

    public function test_mismatched_workspace_id_on_create_is_rejected(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();
        $other = $tenant->environments()->where('slug', 'staging')->firstOrFail();

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            [
                'name' => 'Bad Workspace',
                'method' => 'POST',
                'url_or_path' => 'https://receiver:8080/hook',
                'workspace_id' => $other->public_id,
                'schedule' => [
                    'kind' => 'daily_at',
                    'timezone' => 'UTC',
                    'time' => '09:00',
                ],
            ],
        )->assertUnprocessable();
    }

    public function test_cross_workspace_task_show_returns_not_found(): void
    {
        [$admin, $tenant, $development] = $this->createTenantAdmin();
        $staging = $tenant->environments()->where('slug', 'staging')->firstOrFail();

        $taskId = $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $development, '/tasks'),
            [
                'name' => 'Dev Only',
                'method' => 'POST',
                'url_or_path' => 'https://receiver:8080/hook',
                'schedule' => [
                    'kind' => 'daily_at',
                    'timezone' => 'UTC',
                    'time' => '09:00',
                ],
            ],
        )->json('data.id');

        $this->actingAs($admin)->getJson(
            $this->environmentRoute($tenant, $staging, '/tasks/'.$taskId),
        )->assertNotFound();
    }

    public function test_environment_scoped_audit_includes_workspace_id(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $this->actingAs($admin)->postJson(
            $this->environmentRoute($tenant, $environment, '/tasks'),
            [
                'name' => 'Audited Task',
                'method' => 'POST',
                'url_or_path' => 'https://receiver:8080/hook',
                'schedule' => [
                    'kind' => 'daily_at',
                    'timezone' => 'UTC',
                    'time' => '09:00',
                ],
            ],
        )->assertCreated();

        $log = AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('action', 'task.created')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($environment->id, $log->environment_id);

        $this->actingAs($admin)->getJson(
            $this->tenantRoute($tenant, '/audit-logs?workspace_id='.$environment->public_id),
        )
            ->assertOk()
            ->assertJsonPath('data.0.workspace_id', $environment->public_id)
            ->assertJsonPath('data.0.action', 'task.created');

        $staging = $tenant->environments()->where('slug', 'staging')->firstOrFail();
        $this->actingAs($admin)->getJson(
            $this->tenantRoute($tenant, '/audit-logs?workspace_id='.$staging->public_id),
        )
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_environment_create_audit_includes_workspace_id(): void
    {
        [$admin, $tenant] = $this->createTenantAdmin();

        $created = $this->actingAs($admin)->postJson(
            $this->tenantRoute($tenant, '/environments'),
            ['name' => 'QA Lab', 'slug' => 'qa-lab'],
        )->assertCreated();

        $workspaceId = $created->json('data.workspace_id');
        $this->assertNotEmpty($workspaceId);
        $this->assertSame($created->json('data.id'), $workspaceId);

        $this->actingAs($admin)->getJson(
            $this->tenantRoute($tenant, '/audit-logs?workspace_id='.$workspaceId),
        )
            ->assertOk()
            ->assertJsonPath('data.0.action', 'environment.created')
            ->assertJsonPath('data.0.workspace_id', $workspaceId);
    }

    public function test_environment_resource_exposes_workspace_id_alias(): void
    {
        [$admin, $tenant, $environment] = $this->createTenantAdmin();

        $response = $this->actingAs($admin)->getJson(
            $this->tenantRoute($tenant, '/environments'),
        )->assertOk();

        $match = collect($response->json('data'))->firstWhere('id', $environment->public_id);
        $this->assertNotNull($match);
        $this->assertSame($environment->public_id, $match['workspace_id']);
    }
}
