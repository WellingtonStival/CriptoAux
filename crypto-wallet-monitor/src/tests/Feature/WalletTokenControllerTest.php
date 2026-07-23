<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_sync_tokens(): void
    {
        $wallet = Wallet::factory()->for(User::factory())->create();

        $this->postJson("/api/wallets/{$wallet->id}/tokens/sync")->assertStatus(401);
    }

    public function test_sync_discovers_and_saves_tokens_for_an_ethereum_wallet(): void
    {
        config(['alchemy.api_key' => 'fake-key']);

        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['network' => 'ethereum']);
        Sanctum::actingAs($user);

        Http::fake(function ($request) {
            $method = $request->data()['method'] ?? null;

            if ($method === 'alchemy_getTokenBalances') {
                return Http::response(['result' => ['tokenBalances' => [
                    [
                        'contractAddress' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
                        'tokenBalance' => '0x00000000000000000000000000000000000000000000000000000005f5e100',
                    ],
                ]]]);
            }

            if ($method === 'alchemy_getTokenMetadata') {
                return Http::response(['result' => [
                    'name' => 'USD Coin',
                    'symbol' => 'USDC',
                    'decimals' => 6,
                    'logo' => 'https://static.alchemyapi.io/images/assets/3408.png',
                ]]);
            }

            if (str_contains($request->url(), 'coingecko') || str_contains($request->url(), 'token_price')) {
                return Http::response(['0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48' => ['usd' => 1.0]]);
            }

            return Http::response([], 404);
        });

        $response = $this->postJson("/api/wallets/{$wallet->id}/tokens/sync");

        $response->assertStatus(200)->assertJsonCount(1, 'tokens');
        $this->assertEqualsWithDelta(100.0, $response->json('tokens.0.balance'), 0.00001);
        $this->assertEqualsWithDelta(100.0, $response->json('tokens.0.value_usd'), 0.00001);
        $this->assertSame('https://static.alchemyapi.io/images/assets/3408.png', $response->json('tokens.0.logo_url'));

        $this->assertDatabaseHas('wallet_tokens', [
            'wallet_id' => $wallet->id,
            'contract_address' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
            'symbol' => 'USDC',
            'logo_url' => 'https://static.alchemyapi.io/images/assets/3408.png',
        ]);
    }

    public function test_sync_ignores_tokens_with_implausible_spam_balances(): void
    {
        config(['alchemy.api_key' => 'fake-key']);

        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['network' => 'ethereum']);
        Sanctum::actingAs($user);

        Http::fake(function ($request) {
            $method = $request->data()['method'] ?? null;

            if ($method === 'alchemy_getTokenBalances') {
                return Http::response(['result' => ['tokenBalances' => [
                    [
                        'contractAddress' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
                        'tokenBalance' => '0x00000000000000000000000000000000000000000000000000000005f5e100',
                    ],
                    [
                        'contractAddress' => '0x0027449bf0887ca3e431d263ffdefb244d95b555',
                        'tokenBalance' => '0xffffffffffffffffffffffffffffffff',
                    ],
                ]]]);
            }

            if ($method === 'alchemy_getTokenMetadata') {
                return Http::response(['result' => ['name' => 'USD Coin', 'symbol' => 'USDC', 'decimals' => 6]]);
            }

            return Http::response([], 404);
        });

        $response = $this->postJson("/api/wallets/{$wallet->id}/tokens/sync");

        $response->assertStatus(200)->assertJsonCount(1, 'tokens');
        $this->assertDatabaseHas('wallet_tokens', [
            'wallet_id' => $wallet->id,
            'contract_address' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
        ]);
        $this->assertDatabaseMissing('wallet_tokens', [
            'wallet_id' => $wallet->id,
            'contract_address' => '0x0027449bf0887ca3e431d263ffdefb244d95b555',
        ]);
    }

    public function test_sync_rejects_a_network_without_token_support(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create([
            'address' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
            'network' => 'bitcoin',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/wallets/{$wallet->id}/tokens/sync");

        $response->assertStatus(422);
    }

    public function test_index_lists_tokens_already_tracked_without_calling_the_blockchain(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['network' => 'ethereum']);

        $token = WalletToken::create([
            'wallet_id' => $wallet->id,
            'contract_address' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
            'symbol' => 'USDC',
            'name' => 'USD Coin',
            'decimals' => 6,
        ]);

        $token->balanceHistories()->create([
            'balance' => 42.5,
            'price_usd' => 1.0,
            'captured_at' => now(),
        ]);

        Sanctum::actingAs($user);
        Http::fake();

        $response = $this->getJson("/api/wallets/{$wallet->id}/tokens");

        $response->assertStatus(200)->assertJsonCount(1, 'tokens');
        $this->assertSame(42.5, $response->json('tokens.0.balance'));
        Http::assertNothingSent();
    }

    public function test_user_cannot_sync_tokens_of_another_users_wallet(): void
    {
        $owner = User::factory()->create();
        $wallet = Wallet::factory()->for($owner)->create(['network' => 'ethereum']);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/wallets/{$wallet->id}/tokens/sync")->assertStatus(404);
    }

    public function test_destroy_removes_a_tracked_token(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['network' => 'ethereum']);

        $token = WalletToken::create([
            'wallet_id' => $wallet->id,
            'contract_address' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
            'symbol' => 'USDC',
            'decimals' => 6,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/wallets/{$wallet->id}/tokens/{$token->id}")->assertStatus(200);

        $this->assertDatabaseMissing('wallet_tokens', ['id' => $token->id]);
    }
}
