<?php

namespace Tests\Feature;

use App\Services\Market\PriceService;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PriceServiceTest extends TestCase
{
    private function marketsResponse(): array
    {
        return [
            [
                'id' => 'ethereum',
                'current_price' => 3245.67,
                'price_change_percentage_24h_in_currency' => 2.34,
                'price_change_percentage_7d_in_currency' => -5.5,
                'price_change_percentage_30d_in_currency' => 10.1,
                'market_cap' => 390000000000,
                'total_volume' => 15000000000,
                'high_24h' => 3300.0,
                'low_24h' => 3100.0,
            ],
            [
                'id' => 'solana',
                'current_price' => 145.23,
                'price_change_percentage_24h_in_currency' => -1.12,
                'price_change_percentage_7d_in_currency' => 3.2,
                'price_change_percentage_30d_in_currency' => -8.4,
                'market_cap' => 65000000000,
                'total_volume' => 2000000000,
                'high_24h' => 150.0,
                'low_24h' => 140.0,
            ],
            [
                'id' => 'bitcoin',
                'current_price' => 65432.10,
                'price_change_percentage_24h_in_currency' => 0.87,
                'price_change_percentage_7d_in_currency' => 1.5,
                'price_change_percentage_30d_in_currency' => 4.4,
                'market_cap' => 1280000000000,
                'total_volume' => 30000000000,
                'high_24h' => 66000.0,
                'low_24h' => 64000.0,
            ],
        ];
    }

    public function test_returns_rich_market_data_for_each_supported_coin(): void
    {
        Http::fake([
            '*' => Http::response($this->marketsResponse()),
        ]);

        $prices = app(PriceService::class)->current();

        $this->assertSame(3245.67, $prices['ethereum']['usd']);
        $this->assertSame(2.34, $prices['ethereum']['change_24h']);
        $this->assertSame(-5.5, $prices['ethereum']['change_7d']);
        $this->assertSame(390000000000, $prices['ethereum']['market_cap']);
        $this->assertSame(15000000000, $prices['ethereum']['volume_24h']);
        $this->assertEquals(3300.0, $prices['ethereum']['high_24h']);
        $this->assertEquals(3100.0, $prices['ethereum']['low_24h']);
        $this->assertSame(-1.12, $prices['solana']['change_24h']);
        $this->assertSame(65432.10, $prices['bitcoin']['usd']);
    }

    public function test_caches_the_prices_and_does_not_repeat_the_request(): void
    {
        Http::fake([
            '*' => Http::response($this->marketsResponse()),
        ]);

        $service = app(PriceService::class);

        $service->current();
        $service->current();

        Http::assertSentCount(1);
    }

    public function test_aborts_with_502_when_the_request_fails(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        try {
            app(PriceService::class)->current();

            $this->fail('Esperava uma HttpException com status 502.');
        } catch (HttpException $exception) {
            $this->assertSame(502, $exception->getStatusCode());
        }
    }
}
