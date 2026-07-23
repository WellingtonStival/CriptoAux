<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_see_market_overview(): void
    {
        $this->getJson('/api/market/overview')->assertStatus(401);
    }

    public function test_overview_combines_the_three_indicators(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'alternative.me')) {
                return Http::response([
                    'data' => [
                        ['value' => '40', 'value_classification' => 'Fear', 'timestamp' => '1784764800'],
                    ],
                ]);
            }

            if (str_contains($request->url(), '/global')) {
                return Http::response(['data' => ['market_cap_percentage' => ['btc' => 55.0]]]);
            }

            if (str_contains($request->url(), '/coins/markets')) {
                return Http::response([
                    ['id' => 'bitcoin', 'symbol' => 'btc', 'price_change_percentage_30d_in_currency' => 5.0],
                    ['id' => 'alt-a', 'symbol' => 'alta', 'price_change_percentage_30d_in_currency' => 10.0],
                ]);
            }

            return Http::response([], 404);
        });

        $response = $this->getJson('/api/market/overview');

        $response->assertStatus(200);
        $this->assertSame(40, $response->json('fear_greed.value'));
        $this->assertEqualsWithDelta(55.0, $response->json('global.btc_dominance'), 0.001);
        $this->assertSame(100, $response->json('altcoin_season.value'));
    }

    public function test_fear_greed_history_respects_the_period(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Http::fake(['*' => Http::response([
            'data' => [
                ['value' => '40', 'value_classification' => 'Fear', 'timestamp' => '1784764800'],
                ['value' => '35', 'value_classification' => 'Fear', 'timestamp' => '1784678400'],
            ],
        ])]);

        $response = $this->getJson('/api/market/fear-greed/history?period=1y');

        $response->assertStatus(200)->assertJsonCount(2, 'points');
        $this->assertSame('1y', $response->json('period'));

        Http::assertSent(fn ($request) => ($request->data()['limit'] ?? null) === 365);
    }
}
