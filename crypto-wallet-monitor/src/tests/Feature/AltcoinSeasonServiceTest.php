<?php

namespace Tests\Feature;

use App\Services\Market\AltcoinSeasonService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AltcoinSeasonServiceTest extends TestCase
{
    private function coin(string $id, string $symbol, ?float $change30d): array
    {
        return [
            'id' => $id,
            'symbol' => $symbol,
            'price_change_percentage_30d_in_currency' => $change30d,
        ];
    }

    public function test_computes_percentage_of_altcoins_that_outperformed_bitcoin(): void
    {
        Http::fake([
            '*' => Http::response([
                $this->coin('bitcoin', 'btc', 10.0),
                $this->coin('alt-a', 'alta', 20.0), // outperformou
                $this->coin('alt-b', 'altb', 5.0),  // nao outperformou
                $this->coin('alt-c', 'altc', 15.0), // outperformou
                $this->coin('tether', 'usdt', 0.1), // excluido (stablecoin)
                $this->coin('wrapped-bitcoin', 'wbtc', 9.9), // excluido (wrapped)
            ]),
        ]);

        $result = app(AltcoinSeasonService::class)->current();

        $this->assertSame(67, $result['value']);
        $this->assertSame('Misto', $result['classification']);
        $this->assertStringContainsString('90 dias', $result['methodology']);
    }

    public function test_classifies_as_altcoin_season_when_most_alts_beat_bitcoin(): void
    {
        Http::fake([
            '*' => Http::response([
                $this->coin('bitcoin', 'btc', 1.0),
                $this->coin('alt-a', 'alta', 20.0),
                $this->coin('alt-b', 'altb', 20.0),
                $this->coin('alt-c', 'altc', 20.0),
                $this->coin('alt-d', 'altd', -5.0),
            ]),
        ]);

        $result = app(AltcoinSeasonService::class)->current();

        $this->assertSame(75, $result['value']);
        $this->assertSame('Temporada de Altcoins', $result['classification']);
    }

    public function test_returns_null_when_bitcoin_is_missing_from_the_response(): void
    {
        Http::fake(['*' => Http::response([
            ['id' => 'ethereum', 'symbol' => 'eth', 'price_change_percentage_30d_in_currency' => 5.0],
        ])]);

        $this->assertNull(app(AltcoinSeasonService::class)->current());
    }

    public function test_returns_null_on_failure(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->assertNull(app(AltcoinSeasonService::class)->current());
    }
}
