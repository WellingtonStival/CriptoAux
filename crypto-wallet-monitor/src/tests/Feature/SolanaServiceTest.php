<?php

namespace Tests\Feature;

use App\Services\Blockchain\SolanaService;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SolanaServiceTest extends TestCase
{
    private const VALID_ADDRESS = '4Nd1mBQtrMJVYVfKf2PJy9NZUZdTAsp7D4xWLs4gDB4T';

    public function test_converts_lamports_to_sol_correctly(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['value' => 2_000_000_000], // 2 SOL em lamports
            ]),
        ]);

        $balance = app(SolanaService::class)->getBalance(self::VALID_ADDRESS);

        $this->assertSame(2.0, $balance);
    }

    public function test_caches_the_balance_and_does_not_repeat_the_rpc_call(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['value' => 1_000_000_000],
            ]),
        ]);

        $service = app(SolanaService::class);

        $service->getBalance(self::VALID_ADDRESS);
        $service->getBalance(self::VALID_ADDRESS);

        Http::assertSentCount(1);
    }

    public function test_get_cached_balance_returns_null_on_a_cold_cache(): void
    {
        $balance = app(SolanaService::class)->getCachedBalance(self::VALID_ADDRESS);

        $this->assertNull($balance);
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
            app(SolanaService::class)->getBalance(self::VALID_ADDRESS);

            $this->fail('Esperava uma HttpException com status 502.');
        } catch (HttpException $exception) {
            $this->assertSame(502, $exception->getStatusCode());
        }
    }

    public function test_lists_transactions_with_direction_and_amount(): void
    {
        Http::fake([
            '*' => function ($request) {
                $method = $request->data()['method'] ?? null;

                if ($method === 'getSignaturesForAddress') {
                    return Http::response([
                        'jsonrpc' => '2.0',
                        'id' => 1,
                        'result' => [
                            ['signature' => 'sig-received', 'blockTime' => 1700000000, 'err' => null],
                        ],
                    ]);
                }

                if ($method === 'getTransaction') {
                    return Http::response([
                        'jsonrpc' => '2.0',
                        'id' => 1,
                        'result' => [
                            'transaction' => [
                                'message' => ['accountKeys' => [self::VALID_ADDRESS, 'outro']],
                            ],
                            'meta' => [
                                'preBalances' => [1_000_000_000, 0],
                                'postBalances' => [1_500_000_000, 0],
                            ],
                        ],
                    ]);
                }

                return Http::response([], 404);
            },
        ]);

        $transactions = app(SolanaService::class)->getTransactions(self::VALID_ADDRESS);

        $this->assertCount(1, $transactions);
        $this->assertSame('sig-received', $transactions[0]['hash']);
        $this->assertSame('in', $transactions[0]['direction']);
        $this->assertSame(0.5, $transactions[0]['amount']);
        $this->assertNotNull($transactions[0]['timestamp']);
    }

    public function test_discover_tokens_ignores_zero_balance_accounts(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'result' => [
                    'value' => [
                        [
                            'account' => ['data' => ['parsed' => ['info' => [
                                'mint' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
                                'tokenAmount' => ['amount' => '50000000', 'decimals' => 6, 'uiAmount' => 50.0],
                            ]]]],
                        ],
                        [
                            'account' => ['data' => ['parsed' => ['info' => [
                                'mint' => 'So11111111111111111111111111111111111111112',
                                'tokenAmount' => ['amount' => '0', 'decimals' => 9, 'uiAmount' => 0],
                            ]]]],
                        ],
                    ],
                ],
            ]),
        ]);

        $tokens = app(SolanaService::class)->discoverTokens(self::VALID_ADDRESS);

        $this->assertCount(1, $tokens);
        $this->assertSame('EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v', $tokens[0]['contract_address']);
        $this->assertSame(6, $tokens[0]['decimals']);
        $this->assertSame(50.0, $tokens[0]['balance']);
        $this->assertNull($tokens[0]['symbol']);
    }

    public function test_discover_tokens_resolves_name_and_symbol_via_jupiter(): void
    {
        Http::fake([
            'lite-api.jup.ag/*' => Http::response([
                [
                    'id' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
                    'name' => 'USD Coin',
                    'symbol' => 'USDC',
                    'icon' => 'https://example.com/usdc.png',
                ],
            ]),
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'result' => [
                    'value' => [
                        [
                            'account' => ['data' => ['parsed' => ['info' => [
                                'mint' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
                                'tokenAmount' => ['amount' => '50000000', 'decimals' => 6, 'uiAmount' => 50.0],
                            ]]]],
                        ],
                    ],
                ],
            ]),
        ]);

        $tokens = app(SolanaService::class)->discoverTokens(self::VALID_ADDRESS);

        $this->assertCount(1, $tokens);
        $this->assertSame('USDC', $tokens[0]['symbol']);
        $this->assertSame('USD Coin', $tokens[0]['name']);
        $this->assertSame('https://example.com/usdc.png', $tokens[0]['logo_url']);
    }

    public function test_discover_tokens_keeps_null_names_when_jupiter_fails(): void
    {
        Http::fake([
            'lite-api.jup.ag/*' => Http::response([], 500),
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'result' => [
                    'value' => [
                        [
                            'account' => ['data' => ['parsed' => ['info' => [
                                'mint' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
                                'tokenAmount' => ['amount' => '50000000', 'decimals' => 6, 'uiAmount' => 50.0],
                            ]]]],
                        ],
                    ],
                ],
            ]),
        ]);

        $tokens = app(SolanaService::class)->discoverTokens(self::VALID_ADDRESS);

        $this->assertCount(1, $tokens);
        $this->assertNull($tokens[0]['symbol']);
        $this->assertNull($tokens[0]['name']);
    }

    public function test_get_token_balance_sums_across_multiple_token_accounts(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'result' => [
                    'value' => [
                        ['account' => ['data' => ['parsed' => ['info' => [
                            'tokenAmount' => ['uiAmount' => 10.0],
                        ]]]]],
                        ['account' => ['data' => ['parsed' => ['info' => [
                            'tokenAmount' => ['uiAmount' => 5.5],
                        ]]]]],
                    ],
                ],
            ]),
        ]);

        $balance = app(SolanaService::class)->getTokenBalance(
            self::VALID_ADDRESS,
            'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            6,
        );

        $this->assertSame(15.5, $balance);
    }
}
