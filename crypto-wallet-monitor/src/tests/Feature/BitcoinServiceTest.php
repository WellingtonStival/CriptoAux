<?php

namespace Tests\Feature;

use App\Services\Blockchain\BitcoinService;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BitcoinServiceTest extends TestCase
{
    private const VALID_ADDRESS = 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh';

    public function test_converts_satoshis_to_btc_correctly(): void
    {
        Http::fake([
            '*' => Http::response([
                'chain_stats' => [
                    'funded_txo_sum' => 150_000_000,
                    'spent_txo_sum' => 50_000_000, // saldo liquido: 100_000_000 sats = 1 BTC
                ],
            ]),
        ]);

        $balance = app(BitcoinService::class)->getBalance(self::VALID_ADDRESS);

        $this->assertSame(1.0, $balance);
    }

    public function test_caches_the_balance_and_does_not_repeat_the_request(): void
    {
        Http::fake([
            '*' => Http::response([
                'chain_stats' => [
                    'funded_txo_sum' => 100_000_000,
                    'spent_txo_sum' => 0,
                ],
            ]),
        ]);

        $service = app(BitcoinService::class);

        $service->getBalance(self::VALID_ADDRESS);
        $service->getBalance(self::VALID_ADDRESS);

        Http::assertSentCount(1);
    }

    public function test_aborts_with_502_when_the_response_is_invalid(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'not found'], 404),
        ]);

        try {
            app(BitcoinService::class)->getBalance(self::VALID_ADDRESS);

            $this->fail('Esperava uma HttpException com status 502.');
        } catch (HttpException $exception) {
            $this->assertSame(502, $exception->getStatusCode());
        }
    }
}
