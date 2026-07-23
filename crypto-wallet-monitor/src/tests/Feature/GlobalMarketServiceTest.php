<?php

namespace Tests\Feature;

use App\Services\Market\GlobalMarketService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GlobalMarketServiceTest extends TestCase
{
    public function test_current_returns_dominance_and_market_cap(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'market_cap_percentage' => ['btc' => 56.585, 'eth' => 9.935],
                    'total_market_cap' => ['usd' => 2297265056792.45],
                    'market_cap_change_percentage_24h_usd' => -1.67,
                ],
            ]),
        ]);

        $result = app(GlobalMarketService::class)->current();

        $this->assertSame(56.6, $result['btc_dominance']);
        $this->assertSame(9.9, $result['eth_dominance']);
        $this->assertSame(2297265056792.45, $result['total_market_cap_usd']);
        $this->assertSame(-1.67, $result['market_cap_change_24h']);
    }

    public function test_caches_the_response(): void
    {
        Http::fake([
            '*' => Http::response(['data' => ['market_cap_percentage' => ['btc' => 50.0]]]),
        ]);

        $service = app(GlobalMarketService::class);
        $service->current();
        $service->current();

        Http::assertSentCount(1);
    }

    public function test_returns_null_on_failure(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->assertNull(app(GlobalMarketService::class)->current());
    }
}
