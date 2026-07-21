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
}
