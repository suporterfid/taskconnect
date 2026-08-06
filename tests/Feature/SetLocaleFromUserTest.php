<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\User;
use App\Infrastructure\Persistence\Eloquent\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetLocaleFromUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_render_in_the_users_saved_locale(): void
    {
        $user = User::factory()->create();
        UserPreference::query()->where('user_id', $user->id)->update(['locale' => 'pt-BR']);

        $this->actingAs($user);

        $response = $this->patchJson('/api/v1/me/preferences', [
            'timezone' => 12345,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('deve ser uma string', $response->json('error.details.timezone.0'));
    }

    public function test_validation_errors_default_to_english_without_a_saved_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->patchJson('/api/v1/me/preferences', [
            'timezone' => 12345,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('must be a string', $response->json('error.details.timezone.0'));
    }
}
