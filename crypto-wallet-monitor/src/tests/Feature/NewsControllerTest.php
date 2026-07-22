<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NewsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function rssFeed(): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>CoinDesk</title>
                    <item>
                        <title>Bitcoin notícia geral</title>
                        <link>https://example.com/1</link>
                        <description></description>
                        <pubDate>Wed, 22 Jul 2026 10:00:00 GMT</pubDate>
                    </item>
                </channel>
            </rss>
            XML;
    }

    private function emptyFeed(): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>Fonte vazia</title>
                </channel>
            </rss>
            XML;
    }

    public function test_guest_cannot_see_news(): void
    {
        $this->getJson('/api/news')->assertStatus(401);
    }

    public function test_user_can_see_news_for_all_supported_networks(): void
    {
        Http::fake([
            'coindesk.com/*' => Http::response($this->rssFeed()),
            '*' => Http::response($this->emptyFeed()),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/news');

        $response->assertStatus(200)
            ->assertJsonPath('network', null)
            ->assertJsonCount(1, 'news');
    }

    public function test_user_can_filter_news_by_network(): void
    {
        Http::fake([
            'coindesk.com/*' => Http::response($this->rssFeed()),
            '*' => Http::response($this->emptyFeed()),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/news?network=bitcoin');

        $response->assertStatus(200)
            ->assertJsonPath('network', 'bitcoin')
            ->assertJsonCount(1, 'news');
    }

    public function test_user_can_filter_news_by_other(): void
    {
        $generalNewsFeed = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>CoinDesk</title>
                    <item>
                        <title>Reguladores discutem novas regras para o setor</title>
                        <link>https://example.com/1</link>
                        <description></description>
                        <pubDate>Wed, 22 Jul 2026 10:00:00 GMT</pubDate>
                    </item>
                </channel>
            </rss>
            XML;

        Http::fake([
            'coindesk.com/*' => Http::response($generalNewsFeed),
            '*' => Http::response($this->emptyFeed()),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/news?network=other');

        $response->assertStatus(200)
            ->assertJsonPath('network', 'other')
            ->assertJsonCount(1, 'news');
    }

    public function test_rejects_an_unsupported_network(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/news?network=kaspa');

        $response->assertStatus(422);
    }
}
