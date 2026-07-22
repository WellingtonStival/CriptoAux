<?php

namespace Tests\Feature;

use App\Services\News\NewsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewsServiceTest extends TestCase
{
    private function rssFeed(string $sourceTitle, array $items): string
    {
        $itemsXml = collect($items)->map(fn ($item) => <<<XML
            <item>
                <title>{$item['title']}</title>
                <link>{$item['url']}</link>
                <description>{$item['description']}</description>
                <pubDate>{$item['pubDate']}</pubDate>
            </item>
            XML)->implode('');

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
                <channel>
                    <title>{$sourceTitle}</title>
                    {$itemsXml}
                </channel>
            </rss>
            XML;
    }

    private function emptyFeed(string $sourceTitle = 'Fonte vazia'): string
    {
        return $this->rssFeed($sourceTitle, []);
    }

    public function test_fetches_and_tags_news_by_currency(): void
    {
        Http::fake([
            'coindesk.com/*' => Http::response($this->rssFeed('CoinDesk', [
                [
                    'title' => 'Bitcoin hits new all-time high',
                    'url' => 'https://example.com/btc-news',
                    'description' => 'BTC price surges amid strong demand',
                    'pubDate' => 'Wed, 22 Jul 2026 10:00:00 GMT',
                ],
                [
                    'title' => 'Ethereum network upgrade goes live',
                    'url' => 'https://example.com/eth-news',
                    'description' => 'ETH developers ship a major release',
                    'pubDate' => 'Wed, 22 Jul 2026 09:00:00 GMT',
                ],
            ])),
            '*' => Http::response($this->emptyFeed()),
        ]);

        $news = app(NewsService::class)->latest();

        $this->assertCount(2, $news);
        $this->assertSame('Bitcoin hits new all-time high', $news[0]['title']);
        $this->assertSame(['bitcoin'], $news[0]['currencies']);
        $this->assertSame('CoinDesk', $news[0]['source']);
        $this->assertSame(['ethereum'], $news[1]['currencies']);
    }

    public function test_filters_by_network(): void
    {
        Http::fake([
            'coindesk.com/*' => Http::response($this->rssFeed('CoinDesk', [
                [
                    'title' => 'Bitcoin update',
                    'url' => 'https://example.com/1',
                    'description' => '',
                    'pubDate' => 'Wed, 22 Jul 2026 10:00:00 GMT',
                ],
                [
                    'title' => 'Solana update',
                    'url' => 'https://example.com/2',
                    'description' => '',
                    'pubDate' => 'Wed, 22 Jul 2026 09:00:00 GMT',
                ],
            ])),
            '*' => Http::response($this->emptyFeed()),
        ]);

        $news = app(NewsService::class)->latest('solana');

        $this->assertCount(1, $news);
        $this->assertSame('Solana update', $news[0]['title']);
    }

    public function test_skips_a_feed_that_fails_without_breaking_the_others(): void
    {
        Http::fake([
            'coindesk.com/*' => Http::response('not xml at all', 200),
            'cointelegraph.com/*' => Http::response($this->rssFeed('Cointelegraph', [
                [
                    'title' => 'Bitcoin still working fine',
                    'url' => 'https://example.com/ok',
                    'description' => '',
                    'pubDate' => 'Wed, 22 Jul 2026 10:00:00 GMT',
                ],
            ])),
            '*' => Http::response($this->emptyFeed()),
        ]);

        $news = app(NewsService::class)->latest();

        $this->assertCount(1, $news);
        $this->assertSame('Bitcoin still working fine', $news[0]['title']);
    }

    public function test_caches_the_combined_result_and_does_not_repeat_the_requests(): void
    {
        Http::fake(['*' => Http::response($this->emptyFeed())]);

        $service = app(NewsService::class);

        $service->latest();
        $service->latest('bitcoin');
        $service->latest('ethereum');

        // 3 feeds buscados uma unica vez, independente de quantas vezes
        // latest() e chamado com filtros diferentes
        Http::assertSentCount(3);
    }
}
