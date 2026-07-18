<?php

namespace Tests\Feature\Phase0;

use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase0AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_logout_and_me_flow(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.id', $user->public_id)
            ->assertHeader('X-Request-Id');

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_login_validation_error_uses_api_envelope(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonStructure(['error' => ['code', 'message', 'details', 'request_id']]);
    }
}
