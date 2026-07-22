<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalanceHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CaptureWalletBalancesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_captures_a_snapshot_for_every_wallet(): void
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
        $walletA = Wallet::factory()->for($user)->create();
        $walletB = Wallet::factory()->for($user)->create();

        $this->artisan('wallets:capture-balances')->assertSuccessful();

        $this->assertDatabaseHas('wallet_balance_histories', ['wallet_id' => $walletA->id]);
        $this->assertDatabaseHas('wallet_balance_histories', ['wallet_id' => $walletB->id]);
    }

    public function test_continues_after_a_failure_on_one_wallet(): void
    {
        $user = User::factory()->create();
        $goodWallet = Wallet::factory()->for($user)->create();
        $badWallet = Wallet::factory()->for($user)->create();

        Http::fake([
            // cotacao (CoinGecko) sempre responde bem - so a RPC da blockchain falha
            'coingecko.com/*' => Http::response([
                ['id' => 'ethereum', 'current_price' => 1000, 'price_change_percentage_24h_in_currency' => 1],
            ]),
            '*' => Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0xde0b6b3a7640000'])
                ->push([], 500),
        ]);

        $this->artisan('wallets:capture-balances')->assertSuccessful();

        $this->assertSame(1, WalletBalanceHistory::count());
    }
}
