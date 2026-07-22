<?php

namespace Tests\Feature;

use App\Jobs\RefreshWalletBalance;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalanceHistory;
use App\Services\Blockchain\BlockchainResolver;
use App\Services\Wallet\BalanceHistoryRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Throwable;

class RefreshWalletBalanceJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_captures_a_balance_snapshot_when_the_blockchain_responds(): void
    {
        Http::fake([
            'coingecko.com/*' => Http::response([
                ['id' => 'ethereum', 'current_price' => 1000, 'price_change_percentage_24h_in_currency' => 1],
            ]),
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0xde0b6b3a7640000', // 1 ETH em wei
            ]),
        ]);

        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        $this->runJob($wallet->id);

        $this->assertDatabaseHas('wallet_balance_histories', ['wallet_id' => $wallet->id]);
    }

    public function test_throws_when_the_blockchain_fails_so_the_queue_retries(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        $threw = false;

        try {
            $this->runJob($wallet->id);
        } catch (Throwable) {
            $threw = true;
        }

        $this->assertTrue($threw, 'O job deveria relancar a excecao para o worker poder tentar de novo.');
        $this->assertSame(0, WalletBalanceHistory::count());
    }

    public function test_does_nothing_when_the_wallet_no_longer_exists(): void
    {
        Http::fake(); // qualquer chamada aqui seria um bug

        $this->runJob(999999);

        $this->assertSame(0, WalletBalanceHistory::count());
        Http::assertNothingSent();
    }

    private function runJob(int $walletId): void
    {
        (new RefreshWalletBalance($walletId))->handle(
            app(BlockchainResolver::class),
            app(BalanceHistoryRecorder::class),
        );
    }
}
