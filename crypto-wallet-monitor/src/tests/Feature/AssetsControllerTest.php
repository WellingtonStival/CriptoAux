<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssetsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_see_assets(): void
    {
        $this->getJson('/api/assets')->assertStatus(401);
    }

    public function test_sums_the_same_token_across_multiple_wallets(): void
    {
        $user = User::factory()->create();
        $walletA = Wallet::factory()->for($user)->create(['network' => 'ethereum']);
        $walletB = Wallet::factory()->for($user)->create(['network' => 'ethereum']);

        foreach ([$walletA, $walletB] as $wallet) {
            $token = WalletToken::create([
                'wallet_id' => $wallet->id,
                'contract_address' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
                'symbol' => 'USDC',
                'name' => 'USD Coin',
                'decimals' => 6,
            ]);

            $token->balanceHistories()->create([
                'balance' => 10,
                'price_usd' => 1.0,
                'captured_at' => now(),
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/assets');

        $response->assertStatus(200)->assertJsonCount(1, 'assets');
        $this->assertEqualsWithDelta(20.0, $response->json('assets.0.balance'), 0.00001);
        $this->assertEqualsWithDelta(20.0, $response->json('assets.0.value_usd'), 0.00001);
        $this->assertSame(2, $response->json('assets.0.wallets_count'));
    }

    public function test_does_not_include_another_users_tokens(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherWallet = Wallet::factory()->for($otherUser)->create(['network' => 'ethereum']);

        $token = WalletToken::create([
            'wallet_id' => $otherWallet->id,
            'contract_address' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
            'symbol' => 'USDC',
            'decimals' => 6,
        ]);
        $token->balanceHistories()->create(['balance' => 10, 'price_usd' => 1.0, 'captured_at' => now()]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/assets');

        $response->assertStatus(200)->assertJsonCount(0, 'assets');
    }

    public function test_keeps_the_same_contract_address_separate_per_network(): void
    {
        $user = User::factory()->create();
        $ethWallet = Wallet::factory()->for($user)->create(['network' => 'ethereum']);
        $solWallet = Wallet::factory()->for($user)->create([
            'network' => 'solana',
            'address' => '4Nd1mBQtrMJVYVfKf2PJy9NZUZdTAsp7D4xWLs4gDB4T',
        ]);

        // mesmo endereco "coincidindo" em texto entre as duas redes - nao deveria acontecer
        // na pratica (formatos diferentes), mas o agrupamento deve considerar a rede mesmo assim
        WalletToken::create([
            'wallet_id' => $ethWallet->id,
            'contract_address' => '0xsame',
            'symbol' => 'A',
            'decimals' => 6,
        ])->balanceHistories()->create(['balance' => 10, 'price_usd' => 1.0, 'captured_at' => now()]);

        WalletToken::create([
            'wallet_id' => $solWallet->id,
            'contract_address' => '0xsame',
            'symbol' => 'B',
            'decimals' => 6,
        ])->balanceHistories()->create(['balance' => 5, 'price_usd' => 1.0, 'captured_at' => now()]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/assets');

        $response->assertStatus(200)->assertJsonCount(2, 'assets');
    }
}
