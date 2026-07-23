<?php

namespace Tests\Feature;

use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.bot_token' => 'fake-token',
            'telegram.bot_username' => 'NexfolioTestBot',
        ]);
    }

    public function test_is_configured_reflects_the_bot_token(): void
    {
        $this->assertTrue(app(TelegramService::class)->isConfigured());

        config(['telegram.bot_token' => null]);

        $this->assertFalse(app(TelegramService::class)->isConfigured());
    }

    public function test_link_url_builds_a_deep_link_with_the_code(): void
    {
        $url = app(TelegramService::class)->linkUrl('abc123');

        $this->assertSame('https://t.me/NexfolioTestBot?start=abc123', $url);
    }

    public function test_link_url_returns_null_without_a_bot_username(): void
    {
        config(['telegram.bot_username' => null]);

        $this->assertNull(app(TelegramService::class)->linkUrl('abc123'));
    }

    public function test_send_message_posts_to_the_telegram_api(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $sent = app(TelegramService::class)->sendMessage('999', 'Olá');

        $this->assertTrue($sent);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/botfake-token/sendMessage'
            && $request['chat_id'] === '999'
            && $request['text'] === 'Olá');
    }

    public function test_send_message_does_nothing_without_a_configured_token(): void
    {
        config(['telegram.bot_token' => null]);
        Http::fake();

        $sent = app(TelegramService::class)->sendMessage('999', 'Olá');

        $this->assertFalse($sent);
        Http::assertNothingSent();
    }

    public function test_get_updates_parses_start_messages(): void
    {
        Http::fake([
            '*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 42,
                        'message' => [
                            'chat' => ['id' => 555],
                            'text' => '/start abc123',
                        ],
                    ],
                ],
            ]),
        ]);

        $updates = app(TelegramService::class)->getUpdates(0);

        $this->assertCount(1, $updates);
        $this->assertSame(42, $updates[0]['update_id']);
        $this->assertSame('555', $updates[0]['chat_id']);
        $this->assertSame('/start abc123', $updates[0]['text']);
    }
}
