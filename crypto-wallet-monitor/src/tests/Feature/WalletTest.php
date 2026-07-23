<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_wallets(): void
    {
        $this->getJson('/api/wallets')->assertStatus(401);
    }

    public function test_user_sees_only_their_own_wallets(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Wallet::factory()->for($user)->create();
        Wallet::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/wallets');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_user_can_create_a_wallet(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/wallets', [
            'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
            'network' => 'ethereum',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('wallets', [
            'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_create_a_wallet_with_a_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/wallets', [
            'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
            'network' => 'ethereum',
            'name' => 'Minha carteira fria',
        ]);

        $response->assertStatus(201)->assertJsonPath('name', 'Minha carteira fria');
        $this->assertDatabaseHas('wallets', [
            'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
            'name' => 'Minha carteira fria',
        ]);
    }

    public function test_wallet_address_must_match_ethereum_format(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/wallets', [
            'address' => 'not-an-address',
            'network' => 'ethereum',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('address');
    }

    public function test_user_can_create_a_solana_wallet(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/wallets', [
            'address' => '4Nd1mBQtrMJVYVfKf2PJy9NZUZdTAsp7D4xWLs4gDB4T',
            'network' => 'solana',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('wallets', [
            'address' => '4Nd1mBQtrMJVYVfKf2PJy9NZUZdTAsp7D4xWLs4gDB4T',
            'network' => 'solana',
            'user_id' => $user->id,
        ]);
    }

    public function test_wallet_address_must_match_solana_format(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/wallets', [
            'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045', // formato Ethereum
            'network' => 'solana',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('address');
    }

    public function test_user_can_create_a_bitcoin_wallet(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/wallets', [
            'address' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
            'network' => 'bitcoin',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('wallets', [
            'address' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
            'network' => 'bitcoin',
            'user_id' => $user->id,
        ]);
    }

    public function test_wallet_address_must_match_bitcoin_format(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/wallets', [
            'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045', // formato Ethereum
            'network' => 'bitcoin',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('address');
    }

    public function test_wallet_address_must_be_unique(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create([
            'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/wallets', [
            'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
            'network' => 'ethereum',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('address');
    }

    public function test_same_address_can_be_tracked_on_a_different_evm_network(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create([
            'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
            'network' => 'ethereum',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/wallets', [
            'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
            'network' => 'polygon',
        ]);

        $response->assertStatus(201);
    }

    public function test_network_must_be_supported(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/wallets', [
            'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
            'network' => 'kaspa',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('network');
    }

    public function test_user_can_delete_their_own_wallet(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/wallets/{$wallet->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('wallets', ['id' => $wallet->id]);
    }

    public function test_user_cannot_delete_another_users_wallet(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $wallet = Wallet::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/wallets/{$wallet->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('wallets', ['id' => $wallet->id]);
    }

    public function test_user_can_rename_their_own_wallet(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['name' => null]);
        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/wallets/{$wallet->id}", [
            'name' => 'Carteira do Trezor',
        ]);

        $response->assertStatus(200)->assertJsonPath('name', 'Carteira do Trezor');
        $this->assertDatabaseHas('wallets', [
            'id' => $wallet->id,
            'name' => 'Carteira do Trezor',
        ]);
    }

    public function test_user_cannot_rename_another_users_wallet(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $wallet = Wallet::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/wallets/{$wallet->id}", [
            'name' => 'Tentativa indevida',
        ]);

        $response->assertStatus(404);
    }
}
