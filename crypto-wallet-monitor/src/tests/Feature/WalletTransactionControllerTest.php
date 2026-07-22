<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_see_transactions(): void
    {
        $wallet = Wallet::factory()->for(User::factory())->create();

        $this->getJson("/api/wallets/{$wallet->id}/transactions")->assertStatus(401);
    }

    public function test_user_cannot_see_transactions_of_another_users_wallet(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $wallet = Wallet::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/wallets/{$wallet->id}/transactions")->assertStatus(404);
    }

    public function test_returns_unsupported_for_ethereum_wallets(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['network' => 'ethereum']);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/wallets/{$wallet->id}/transactions");

        $response->assertStatus(200)
            ->assertJsonPath('supported', false)
            ->assertJsonPath('transactions', []);
    }

    public function test_returns_transactions_for_a_bitcoin_wallet(): void
    {
        Http::fake([
            '*' => Http::response([
                [
                    'txid' => 'tx-1',
                    'status' => ['block_time' => 1700000000],
                    'vin' => [],
                    'vout' => [
                        ['scriptpubkey_address' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', 'value' => 100_000_000],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create([
            'address' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
            'network' => 'bitcoin',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/wallets/{$wallet->id}/transactions");

        $response->assertStatus(200)
            ->assertJsonPath('supported', true)
            ->assertJsonPath('symbol', 'BTC')
            ->assertJsonCount(1, 'transactions')
            ->assertJsonPath('transactions.0.direction', 'in');
    }
}
