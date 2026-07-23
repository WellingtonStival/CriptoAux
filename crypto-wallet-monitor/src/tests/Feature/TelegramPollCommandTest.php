<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramPollCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.bot_token' => 'fake-token',
            'telegram.bot_username' => 'NexfolioTestBot',
        ]);
    }

    public function test_links_the_account_matching_the_start_code(): void
    {
        $user = User::factory()->create(['telegram_link_code' => 'abc123']);

        Http::fake([
            '*getUpdates*' => Http::response(['result' => [
                ['update_id' => 1, 'message' => ['chat' => ['id' => 777], 'text' => '/start abc123']],
            ]]),
            '*sendMessage*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('telegram:poll')->assertSuccessful();

        $user->refresh();
        $this->assertSame('777', $user->telegram_chat_id);
        $this->assertNull($user->telegram_link_code);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }

    public function test_ignores_a_start_code_that_matches_no_user(): void
    {
        Http::fake([
            '*getUpdates*' => Http::response(['result' => [
                ['update_id' => 1, 'message' => ['chat' => ['id' => 777], 'text' => '/start nao-existe']],
            ]]),
        ]);

        $this->artisan('telegram:poll')->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }

    public function test_does_not_reprocess_updates_already_seen(): void
    {
        Cache::forever('telegram_poll_offset', 5);

        Http::fake(['*getUpdates*' => Http::response(['result' => []])]);

        $this->artisan('telegram:poll')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'offset=5'));
    }

    public function test_does_nothing_without_a_configured_bot_token(): void
    {
        config(['telegram.bot_token' => null]);
        Http::fake();

        $this->artisan('telegram:poll')->assertSuccessful();

        Http::assertNothingSent();
    }
}
