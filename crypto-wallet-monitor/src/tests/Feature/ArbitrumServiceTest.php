<?php

namespace Tests\Feature;

use App\Services\Blockchain\ArbitrumService;
use App\Services\Blockchain\EthereumService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ArbitrumServiceTest extends TestCase
{
    private const ADDRESS = '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045';

    public function test_converts_wei_to_eth_correctly(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x1bc16d674ec80000', // 2 ETH em wei
            ]),
        ]);

        $balance = app(ArbitrumService::class)->getBalance(self::ADDRESS);

        $this->assertSame(2.0, $balance);
        Http::assertSent(fn ($request) => $request->url() === config('blockchain.arbitrum.rpc_url'));
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
            app(ArbitrumService::class)->getBalance(self::ADDRESS);
            $this->fail('Esperava uma HttpException com status 502.');
        } catch (HttpException $exception) {
            $this->assertSame(502, $exception->getStatusCode());
        }
    }

    public function test_symbol_and_address_pattern(): void
    {
        $service = app(ArbitrumService::class);

        $this->assertSame('ETH', $service->symbol());
        $this->assertSame(1, preg_match($service->addressPattern(), self::ADDRESS));
    }

    /**
     * Arbitrum usa "ETH" como simbolo, igual a Ethereum mainnet - o cache
     * precisa ser isolado por rede (nao por simbolo), senao consultar o
     * saldo numa rede devolveria o saldo cacheado da outra pro mesmo
     * endereco.
     */
    public function test_cache_key_does_not_collide_with_ethereum_mainnet(): void
    {
        Cache::put('ethereum_balance:' . strtolower(self::ADDRESS), 999.0, now()->addSeconds(60));

        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x1bc16d674ec80000', // 2 ETH em wei
            ]),
        ]);

        $arbitrumBalance = app(ArbitrumService::class)->getBalance(self::ADDRESS);
        $ethereumBalance = app(EthereumService::class)->getCachedBalance(self::ADDRESS);

        $this->assertSame(2.0, $arbitrumBalance);
        $this->assertSame(999.0, $ethereumBalance);
    }

    public function test_discover_tokens_ignores_the_native_pseudo_contract_and_uses_alchemy(): void
    {
        config(['alchemy.api_key' => 'fake-key']);

        Http::fake(function ($request) {
            $method = $request->data()['method'] ?? null;

            if ($method === 'alchemy_getTokenBalances') {
                $this->assertStringContainsString('arb-mainnet.g.alchemy.com', $request->url());

                return Http::response(['result' => ['tokenBalances' => [
                    [
                        'contractAddress' => '0x0000000000000000000000000000000000001010',
                        'tokenBalance' => '0x1bc16d674ec80000',
                    ],
                    [
                        'contractAddress' => '0x0000000000000000000000000000000000dead',
                        'tokenBalance' => '0x00000000000000000000000000000000000000000000000000000005f5e100',
                    ],
                ]]]);
            }

            if ($method === 'alchemy_getTokenMetadata') {
                return Http::response(['result' => ['name' => 'Some Token', 'symbol' => 'SMT', 'decimals' => 6]]);
            }

            return Http::response([], 404);
        });

        $tokens = app(ArbitrumService::class)->discoverTokens(self::ADDRESS);

        $this->assertCount(1, $tokens);
        $this->assertSame('0x0000000000000000000000000000000000dead', $tokens[0]['contract_address']);
    }
}
