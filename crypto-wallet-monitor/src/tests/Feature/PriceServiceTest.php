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

    /**
     * Arbitrum nao tem moeda nativa propria - o gas e pago em ETH, entao
     * a rede compartilha o coin id "ethereum" com a mainnet. Isso exige
     * que current() consiga preencher DUAS redes a partir da MESMA linha
     * da resposta da CoinGecko, nao so a primeira que bater.
     */
    public function test_fills_arbitrum_from_the_same_coingecko_row_as_ethereum_mainnet(): void
    {
        Http::fake([
            '*' => Http::response($this->marketsResponse()),
        ]);

        $prices = app(PriceService::class)->current();

        $this->assertSame(3245.67, $prices['arbitrum']['usd']);
        $this->assertSame(2.34, $prices['arbitrum']['change_24h']);
    }

    public function test_maps_polygon_bnb_and_avalanche_coin_ids_that_dont_match_the_network_key(): void
    {
        Http::fake([
            '*' => Http::response([
                [
                    'id' => 'polygon-ecosystem-token',
                    'current_price' => 0.078,
                    'price_change_percentage_24h_in_currency' => 1.1,
                ],
                [
                    'id' => 'binancecoin',
                    'current_price' => 569.45,
                    'price_change_percentage_24h_in_currency' => -0.2,
                ],
                [
                    'id' => 'avalanche-2',
                    'current_price' => 24.5,
                    'price_change_percentage_24h_in_currency' => 3.4,
                ],
            ]),
        ]);

        $prices = app(PriceService::class)->current();

        $this->assertSame(0.078, $prices['polygon']['usd']);
        $this->assertSame(569.45, $prices['bnb']['usd']);
        $this->assertSame(24.5, $prices['avalanche']['usd']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'polygon-ecosystem-token')
            && str_contains($request->url(), 'binancecoin')
            && str_contains($request->url(), 'avalanche-2'));
    }

    public function test_token_prices_uses_the_correct_coingecko_platform_id(): void
    {
        Http::fake(['*' => Http::response([])]);

        app(PriceService::class)->tokenPrices('polygon', ['0x0000000000000000000000000000000000dead']);
        app(PriceService::class)->tokenPrices('bnb', ['0x0000000000000000000000000000000000beef']);
        app(PriceService::class)->tokenPrices('avalanche', ['0x0000000000000000000000000000000000cafe']);
        app(PriceService::class)->tokenPrices('arbitrum', ['0x0000000000000000000000000000000000feed']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/simple/token_price/polygon-pos'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/simple/token_price/binance-smart-chain'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/simple/token_price/avalanche'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/simple/token_price/arbitrum-one'));
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

    public function test_token_prices_returns_price_by_contract_address(): void
    {
        Http::fake([
            '*' => Http::response([
                '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48' => ['usd' => 1.0007],
            ]),
        ]);

        $prices = app(PriceService::class)->tokenPrices('ethereum', [
            '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
        ]);

        $this->assertEqualsWithDelta(1.0007, $prices['0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48'], 0.00001);
    }

    public function test_token_prices_returns_empty_array_for_no_addresses(): void
    {
        Http::fake();

        $prices = app(PriceService::class)->tokenPrices('ethereum', []);

        $this->assertSame([], $prices);
        Http::assertNothingSent();
    }

    public function test_token_prices_degrades_gracefully_on_failure(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $prices = app(PriceService::class)->tokenPrices('ethereum', ['0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48']);

        $this->assertSame([], $prices);
    }

    public function test_token_prices_sends_one_address_per_request_without_an_api_key(): void
    {
        config(['market.coingecko.api_key' => null]);
        Http::fake(['*' => Http::response([])]);

        app(PriceService::class)->tokenPrices('ethereum', [
            '0x0000000000000000000000000000000000dead',
            '0x0000000000000000000000000000000000beef',
        ]);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => !$request->hasHeader('x-cg-demo-api-key'));
    }

    public function test_token_prices_batches_up_to_100_per_request_with_an_api_key(): void
    {
        config(['market.coingecko.api_key' => 'fake-demo-key']);
        Http::fake(['*' => Http::response([])]);

        $addresses = array_map(fn (int $i) => sprintf('0x%040x', $i), range(1, 150));

        app(PriceService::class)->tokenPrices('ethereum', $addresses);

        // 150 enderecos, 100 por lote -> 2 requisicoes (100 + 50)
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->header('x-cg-demo-api-key')[0] === 'fake-demo-key');
    }
}
