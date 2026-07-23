<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelegramControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['telegram.bot_token' => 'fake-token', 'telegram.bot_username' => 'NexfolioTestBot']);
    }

    public function test_guest_cannot_see_status(): void
    {
        $this->getJson('/api/telegram/status')->assertStatus(401);
    }

    public function test_status_reflects_whether_the_user_is_linked(): void
    {
        Sanctum::actingAs(User::factory()->create(['telegram_chat_id' => '123']));

        $this->getJson('/api/telegram/status')
            ->assertStatus(200)
            ->assertJson(['linked' => true, 'configured' => true]);
    }

    public function test_generate_link_code_saves_a_code_and_returns_the_deep_link(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/telegram/link-code');

        $response->assertStatus(200);
        $code = $response->json('code');

        $this->assertNotEmpty($code);
        $this->assertSame("https://t.me/NexfolioTestBot?start={$code}", $response->json('link_url'));
        $this->assertSame($code, $user->fresh()->telegram_link_code);
    }

    public function test_generate_link_code_fails_when_telegram_is_not_configured(): void
    {
        config(['telegram.bot_token' => null]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/telegram/link-code')->assertStatus(422);
    }

    public function test_unlink_clears_the_chat_id(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '123']);
        Sanctum::actingAs($user);

        $this->postJson('/api/telegram/unlink')->assertStatus(200);

        $this->assertNull($user->fresh()->telegram_chat_id);
    }
}
