<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_verify_email_with_a_valid_token(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'confirmar@example.com']);
        $plainToken = $user->sendEmailVerificationNotification();

        $response = $this->postJson('/api/email/verify', [
            'token' => $plainToken,
            'email' => 'confirmar@example.com',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['user', 'token']);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->email_verification_token);
    }

    public function test_verification_fails_with_a_wrong_token(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'confirmar@example.com']);
        $user->sendEmailVerificationNotification();

        $response = $this->postJson('/api/email/verify', [
            'token' => 'token-errado',
            'email' => 'confirmar@example.com',
        ]);

        $response->assertStatus(422);
        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_verification_fails_for_an_unknown_email(): void
    {
        $response = $this->postJson('/api/email/verify', [
            'token' => 'qualquer-coisa',
            'email' => 'nao-existe@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_a_used_token_cannot_be_reused(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'confirmar@example.com']);
        $plainToken = $user->sendEmailVerificationNotification();

        $this->postJson('/api/email/verify', ['token' => $plainToken, 'email' => 'confirmar@example.com'])
            ->assertStatus(200);

        $this->postJson('/api/email/verify', ['token' => $plainToken, 'email' => 'confirmar@example.com'])
            ->assertStatus(422);
    }

    public function test_resend_sends_a_new_notification_for_an_unverified_user(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create(['email' => 'confirmar@example.com']);

        $response = $this->postJson('/api/email/resend', ['email' => 'confirmar@example.com']);

        $response->assertStatus(200);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resend_does_not_reveal_whether_the_email_exists(): void
    {
        Notification::fake();

        $responseUnknown = $this->postJson('/api/email/resend', ['email' => 'nao-existe@example.com']);
        $responseKnown = $this->postJson('/api/email/resend', ['email' => 'nao-existe@example.com']);

        $responseUnknown->assertStatus(200);
        $this->assertSame($responseUnknown->json('message'), $responseKnown->json('message'));
    }

    public function test_resend_does_nothing_for_an_already_verified_user(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'javerificado@example.com']);

        $this->postJson('/api/email/resend', ['email' => 'javerificado@example.com'])
            ->assertStatus(200);

        Notification::assertNotSentTo($user, VerifyEmailNotification::class);
    }
}
