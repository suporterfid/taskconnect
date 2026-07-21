<?php

namespace Tests\Feature\Phase0;

use App\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_and_reset_password_allows_login_with_new_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset-me@example.com',
            'password' => Hash::make('OldPassword1!'),
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk()
            ->assertJsonPath('data.message', 'If an account exists for that email, a reset link has been sent.');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->assertNotNull($token);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertOk()
            ->assertJsonPath('data.message', 'Password has been reset.');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'OldPassword1!',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'NewPassword1!',
        ])->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }
}
