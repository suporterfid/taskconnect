<?php

namespace Tests\Feature\Phase0;

use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(sha1('|127.0.0.1'));
    }

    public function test_login_is_throttled_after_ten_requests(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);

        $payload = [
            'email' => $user->email,
            'password' => 'wrong-password',
        ];

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', $payload)
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', $payload)
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'too_many_requests');
    }
}
