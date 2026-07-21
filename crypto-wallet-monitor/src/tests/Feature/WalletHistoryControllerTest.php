<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalanceHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_see_wallet_history(): void
    {
        $wallet = Wallet::factory()->for(User::factory())->create();

        $this->getJson("/api/wallets/{$wallet->id}/history")->assertStatus(401);
    }

    public function test_user_cannot_see_history_of_another_users_wallet(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $wallet = Wallet::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/wallets/{$wallet->id}/history")->assertStatus(404);
    }

    public function test_returns_empty_summary_when_there_is_no_history_yet(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/wallets/{$wallet->id}/history");

        $response->assertStatus(200)
            ->assertJsonPath('points', [])
            ->assertJsonPath('summary.current_value_usd', null)
            ->assertJsonPath('summary.change_percent', null);
    }

    public function test_filters_points_by_period(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        Sanctum::actingAs($user);

        WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
            'balance' => 1,
            'price_usd' => 1000,
            'captured_at' => now()->subDays(40), // fora dos 7d e 30d
        ]);

        WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
            'balance' => 1.1,
            'price_usd' => 1100,
            'captured_at' => now()->subDays(2), // dentro dos 7d
        ]);

        $response = $this->getJson("/api/wallets/{$wallet->id}/history?period=7d");

        $response->assertStatus(200)->assertJsonCount(1, 'points');

        $response = $this->getJson("/api/wallets/{$wallet->id}/history?period=30d");

        $response->assertStatus(200)->assertJsonCount(1, 'points');

        $response = $this->getJson("/api/wallets/{$wallet->id}/history?period=all");

        $response->assertStatus(200)->assertJsonCount(2, 'points');
    }

    public function test_computes_change_and_min_max_correctly(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        Sanctum::actingAs($user);

        // valor inicial: 1 * 1000 = 1000 USD
        WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
            'balance' => 1,
            'price_usd' => 1000,
            'captured_at' => now()->subDays(3),
        ]);

        // valor minimo: 1 * 800 = 800 USD
        WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
            'balance' => 1,
            'price_usd' => 800,
            'captured_at' => now()->subDays(2),
        ]);

        // valor final: 1 * 1200 = 1200 USD
        WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
            'balance' => 1,
            'price_usd' => 1200,
            'captured_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/wallets/{$wallet->id}/history?period=7d");

        $response->assertStatus(200)
            ->assertJsonPath('summary.current_value_usd', 1200)
            ->assertJsonPath('summary.change_value_usd', 200)
            ->assertJsonPath('summary.min_value_usd', 800)
            ->assertJsonPath('summary.max_value_usd', 1200);

        $this->assertEqualsWithDelta(20.0, $response->json('summary.change_percent'), 0.0001);
    }
}
