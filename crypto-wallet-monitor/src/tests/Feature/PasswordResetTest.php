<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/forgot-password', ['email' => $user->email]);

        $response->assertStatus(200);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user) {
                $mail = $notification->toMail($user);

                return str_contains($mail->actionUrl, '/redefinir-senha')
                    && str_contains($mail->actionUrl, urlencode($user->email));
            }
        );
    }

    public function test_forgot_password_does_not_reveal_whether_the_email_exists(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'nao-cadastrado@example.com',
        ]);

        $response->assertStatus(200);
        Notification::assertNothingSent();
    }

    public function test_forgot_password_requires_a_valid_email(): void
    {
        $response = $this->postJson('/api/forgot-password', ['email' => 'not-an-email']);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword1',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('newpassword1', $user->fresh()->password));
    }

    public function test_reset_fails_with_an_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/reset-password', [
            'token' => 'token-invalido',
            'email' => $user->email,
            'password' => 'newpassword1',
        ]);

        $response->assertStatus(422);
    }

    public function test_reset_requires_a_strong_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'weak',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }
}
