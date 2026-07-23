<?php

namespace Tests\Feature;

use App\Services\Blockchain\EthereumService;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EthereumServiceTest extends TestCase
{
    public function test_converts_wei_to_eth_correctly(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x1bc16d674ec80000', // 2 ETH em wei
            ]),
        ]);

        $balance = app(EthereumService::class)
            ->getBalance('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');

        $this->assertSame(2.0, $balance);
    }

    public function test_caches_the_balance_and_does_not_repeat_the_rpc_call(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0xde0b6b3a7640000',
            ]),
        ]);

        $service = app(EthereumService::class);
        $address = '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045';

        $service->getBalance($address);
        $service->getBalance($address);

        Http::assertSentCount(1);
    }

    public function test_get_cached_balance_returns_null_on_a_cold_cache(): void
    {
        $balance = app(EthereumService::class)
            ->getCachedBalance('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');

        $this->assertNull($balance);
    }

    public function test_get_cached_balance_returns_the_value_after_a_live_fetch(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x1bc16d674ec80000', // 2 ETH em wei
            ]),
        ]);

        $service = app(EthereumService::class);
        $address = '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045';

        $service->getBalance($address);

        $this->assertSame(2.0, $service->getCachedBalance($address));
        Http::assertSentCount(1);
    }

    public function test_aborts_with_502_when_the_rpc_returns_an_error(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'error' => ['code' => -32000, 'message' => 'boom'],
            ]),
        ]);

        try {
            app(EthereumService::class)
                ->getBalance('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');

            $this->fail('Esperava uma HttpException com status 502.');
        } catch (HttpException $exception) {
            $this->assertSame(502, $exception->getStatusCode());
        }
    }

    public function test_discover_tokens_returns_empty_without_an_alchemy_key(): void
    {
        config(['alchemy.api_key' => null]);

        $tokens = app(EthereumService::class)->discoverTokens('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');

        $this->assertSame([], $tokens);
        Http::assertNothingSent();
    }

    public function test_discover_tokens_fetches_balances_and_metadata(): void
    {
        config(['alchemy.api_key' => 'fake-key']);

        Http::fake(function ($request) {
            $method = $request->data()['method'] ?? null;

            if ($method === 'alchemy_getTokenBalances') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'result' => [
                        'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
                        'tokenBalances' => [
                            [
                                'contractAddress' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
                                'tokenBalance' => '0x00000000000000000000000000000000000000000000000000000005f5e100', // 100_000_000
                            ],
                            [
                                // saldo zero - deve ser ignorado
                                'contractAddress' => '0x0000000000000000000000000000000000dead',
                                'tokenBalance' => '0x0000000000000000000000000000000000000000000000000000000000000000',
                            ],
                        ],
                    ],
                ]);
            }

            if ($method === 'alchemy_getTokenMetadata') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'result' => [
                        'name' => 'USD Coin',
                        'symbol' => 'USDC',
                        'decimals' => 6,
                        'logo' => 'https://static.alchemyapi.io/images/assets/3408.png',
                    ],
                ]);
            }

            return Http::response([], 404);
        });

        $tokens = app(EthereumService::class)->discoverTokens('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');

        $this->assertCount(1, $tokens);
        $this->assertSame('0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', $tokens[0]['contract_address']);
        $this->assertSame('USDC', $tokens[0]['symbol']);
        $this->assertSame('USD Coin', $tokens[0]['name']);
        $this->assertSame('https://static.alchemyapi.io/images/assets/3408.png', $tokens[0]['logo_url']);
        $this->assertSame(6, $tokens[0]['decimals']);
        $this->assertEqualsWithDelta(100.0, $tokens[0]['balance'], 0.00001);
    }

    public function test_get_token_balance_calls_eth_call_with_balance_of_selector(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'result' => '0x00000000000000000000000000000000000000000000000000000005f5e100', // 100_000_000
            ]),
        ]);

        $balance = app(EthereumService::class)->getTokenBalance(
            '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
            '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
            6,
        );

        $this->assertEqualsWithDelta(100.0, $balance, 0.00001);

        Http::assertSent(function ($request) {
            return str_starts_with($request->data()['params'][0]['data'], '0x70a08231');
        });
    }
}
