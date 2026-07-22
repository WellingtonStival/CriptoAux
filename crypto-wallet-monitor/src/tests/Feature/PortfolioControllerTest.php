<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalanceHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortfolioControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_see_portfolio_history(): void
    {
        $this->getJson('/api/portfolio/history')->assertStatus(401);
    }

    public function test_returns_empty_data_for_a_user_without_wallets(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/portfolio/history');

        $response->assertStatus(200)
            ->assertJsonPath('points', [])
            ->assertJsonPath('allocation', [])
            ->assertJsonPath('summary.current_value_usd', 0);
    }

    public function test_aggregates_value_across_multiple_wallets(): void
    {
        $user = User::factory()->create();
        $ethWallet = Wallet::factory()->for($user)->create(['network' => 'ethereum']);
        $solWallet = Wallet::factory()->for($user)->create(['network' => 'solana']);

        // mesmo "balde" de hora - devem ser somados num unico ponto
        $now = now();

        WalletBalanceHistory::create([
            'wallet_id' => $ethWallet->id,
            'network' => 'ethereum',
            'balance' => 1,
            'price_usd' => 1000,
            'captured_at' => $now,
        ]);

        WalletBalanceHistory::create([
            'wallet_id' => $solWallet->id,
            'network' => 'solana',
            'balance' => 10,
            'price_usd' => 50,
            'captured_at' => $now->copy()->addMinutes(2),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/portfolio/history?period=24h');

        $response->assertStatus(200)->assertJsonCount(1, 'points');
        $this->assertEqualsWithDelta(1500.0, $response->json('points.0.value_usd'), 0.001);
        $this->assertEqualsWithDelta(1500.0, $response->json('summary.current_value_usd'), 0.001);
    }

    public function test_computes_allocation_by_network(): void
    {
        $user = User::factory()->create();
        $ethWallet = Wallet::factory()->for($user)->create(['network' => 'ethereum']);
        $solWallet = Wallet::factory()->for($user)->create(['network' => 'solana']);

        WalletBalanceHistory::create([
            'wallet_id' => $ethWallet->id,
            'network' => 'ethereum',
            'balance' => 1,
            'price_usd' => 1500, // 1500 USD (75%)
            'captured_at' => now(),
        ]);

        WalletBalanceHistory::create([
            'wallet_id' => $solWallet->id,
            'network' => 'solana',
            'balance' => 10,
            'price_usd' => 50, // 500 USD (25%)
            'captured_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/portfolio/history');

        $allocation = collect($response->json('allocation'))->keyBy('network');

        $this->assertEqualsWithDelta(75.0, $allocation['ethereum']['percent'], 0.01);
        $this->assertEqualsWithDelta(25.0, $allocation['solana']['percent'], 0.01);
    }

    public function test_uses_the_latest_snapshot_per_wallet_within_a_bucket(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['network' => 'ethereum']);
        $now = now();

        WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => 'ethereum',
            'balance' => 1,
            'price_usd' => 1000,
            'captured_at' => $now,
        ]);

        // segundo snapshot da MESMA wallet no mesmo balde de hora - so o
        // mais recente deve contar, nao a soma dos dois
        WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => 'ethereum',
            'balance' => 1,
            'price_usd' => 1100,
            'captured_at' => $now->copy()->addMinutes(10),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/portfolio/history?period=24h');

        $response->assertJsonCount(1, 'points');
        $this->assertEqualsWithDelta(1100.0, $response->json('points.0.value_usd'), 0.001);
    }

    public function test_does_not_include_another_users_wallets(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherWallet = Wallet::factory()->for($otherUser)->create();

        WalletBalanceHistory::create([
            'wallet_id' => $otherWallet->id,
            'network' => $otherWallet->network,
            'balance' => 100,
            'price_usd' => 1000,
            'captured_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/portfolio/history');

        $response->assertJsonPath('points', [])
            ->assertJsonPath('summary.current_value_usd', 0);
    }
}
