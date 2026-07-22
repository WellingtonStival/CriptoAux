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
}
