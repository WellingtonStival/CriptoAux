<?php

namespace Tests\Feature;

use App\Jobs\RefreshWalletBalance;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalanceHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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

    public function test_returns_502_when_the_rpc_request_fails_and_there_is_no_history_to_fall_back_to(): void
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

    public function test_marks_a_fresh_balance_as_not_stale(): void
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

        $response->assertStatus(200)->assertJsonPath('stale', false);
    }

    public function test_returns_cached_balance_instantly_without_touching_the_blockchain(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        Sanctum::actingAs($user);

        Cache::put('eth_balance:' . strtolower($wallet->address), 9.5, now()->addSeconds(60));

        Http::fake(); // qualquer chamada aqui seria um bug - o cache deveria bastar

        $response = $this->getJson("/api/wallets/{$wallet->id}/balance");

        $response->assertStatus(200)->assertJsonPath('stale', false);
        $this->assertEqualsWithDelta(9.5, $response->json('balance'), 0.00000001);
        Http::assertNothingSent();
    }

    public function test_serves_last_known_balance_and_dispatches_a_background_refresh_when_cache_is_cold(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
            'balance' => 1.23456789,
            'price_usd' => 3000,
            'captured_at' => now()->subHours(2),
        ]);

        Http::fake(); // nao deveria ser chamado - a resposta deve vir do historico

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/wallets/{$wallet->id}/balance");

        $response->assertStatus(200)->assertJsonPath('stale', true);
        $this->assertEqualsWithDelta(1.23456789, $response->json('balance'), 0.00000001);
        Http::assertNothingSent();

        Queue::assertPushed(RefreshWalletBalance::class, function ($job) use ($wallet) {
            return $job->walletId === $wallet->id;
        });
    }

    public function test_force_true_always_fetches_live_even_with_a_warm_cache(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        Sanctum::actingAs($user);

        Cache::put('eth_balance:' . strtolower($wallet->address), 9.5, now()->addSeconds(60));

        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0xde0b6b3a7640000', // 1 ETH em wei
            ]),
        ]);

        $response = $this->getJson("/api/wallets/{$wallet->id}/balance?force=true");

        $response->assertStatus(200)->assertJsonPath('stale', false);
        $this->assertEqualsWithDelta(1.0, $response->json('balance'), 0.00000001);
    }

    public function test_force_true_falls_back_to_last_known_balance_on_failure(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
            'balance' => 1.23456789,
            'price_usd' => 3000,
            'captured_at' => now()->subHours(2),
        ]);

        Http::fake(['*' => Http::response([], 500)]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/wallets/{$wallet->id}/balance?force=true");

        $response->assertStatus(200)->assertJsonPath('stale', true);
        $this->assertEqualsWithDelta(1.23456789, $response->json('balance'), 0.00000001);
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
