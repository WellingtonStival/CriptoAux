<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_the_balance_of_their_wallet(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0xde0b6b3a7640000', // 1 ETH em wei
            ]),
        ]);

        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/wallets/{$wallet->id}/balance");

        $response->assertStatus(200)
            ->assertJsonPath('wallet_id', $wallet->id)
            ->assertJsonPath('symbol', 'ETH');

        $this->assertEqualsWithDelta(1.0, $response->json('balance'), 0.00000001);
    }

    public function test_user_can_get_the_balance_of_a_solana_wallet(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['value' => 1_500_000_000], // 1.5 SOL em lamports
            ]),
        ]);

        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create([
            'address' => '4Nd1mBQtrMJVYVfKf2PJy9NZUZdTAsp7D4xWLs4gDB4T',
            'network' => 'solana',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/wallets/{$wallet->id}/balance");

        $response->assertStatus(200)
            ->assertJsonPath('wallet_id', $wallet->id)
            ->assertJsonPath('symbol', 'SOL');

        $this->assertEqualsWithDelta(1.5, $response->json('balance'), 0.00000001);
    }

    public function test_user_can_get_the_balance_of_a_bitcoin_wallet(): void
    {
        Http::fake([
            '*' => Http::response([
                'chain_stats' => [
                    'funded_txo_sum' => 250_000_000,
                    'spent_txo_sum' => 50_000_000, // saldo liquido: 2 BTC
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create([
            'address' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
            'network' => 'bitcoin',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/wallets/{$wallet->id}/balance");

        $response->assertStatus(200)
            ->assertJsonPath('wallet_id', $wallet->id)
            ->assertJsonPath('symbol', 'BTC');

        $this->assertEqualsWithDelta(2.0, $response->json('balance'), 0.00000001);
    }

    public function test_checking_balance_records_a_history_snapshot(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0xde0b6b3a7640000', // 1 ETH em wei
            ]),
        ]);

        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/wallets/{$wallet->id}/balance");

        $this->assertDatabaseHas('wallet_balance_histories', [
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
        ]);
    }

    public function test_returns_502_when_the_rpc_request_fails(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/wallets/{$wallet->id}/balance");

        $response->assertStatus(502);
    }

    public function test_returns_502_when_the_rpc_returns_an_error(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'error' => ['code' => -32000, 'message' => 'boom'],
            ]),
        ]);

        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/wallets/{$wallet->id}/balance");

        $response->assertStatus(502);
    }

    public function test_user_cannot_get_the_balance_of_another_users_wallet(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $wallet = Wallet::factory()->for($otherUser)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/wallets/{$wallet->id}/balance");

        $response->assertStatus(404);
    }
}
