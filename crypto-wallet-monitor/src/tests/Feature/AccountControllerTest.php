<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalanceHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_see_account(): void
    {
        $this->getJson('/api/account')->assertStatus(401);
    }

    public function test_shows_the_current_users_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Wellington',
            'email' => 'wellington@example.com',
            'telegram_chat_id' => '999',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/account');

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Wellington')
            ->assertJsonPath('email', 'wellington@example.com')
            ->assertJsonPath('email_verified', true)
            ->assertJsonPath('telegram_linked', true);
    }

    public function test_user_can_update_their_name(): void
    {
        $user = User::factory()->create(['name' => 'Nome Antigo']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/account', ['name' => 'Nome Novo'])->assertStatus(200);

        $this->assertSame('Nome Novo', $user->fresh()->name);
    }

    public function test_user_can_change_their_password(): void
    {
        $user = User::factory()->create(); // senha padrao: "password"
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/account/password', [
            'current_password' => 'password',
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('novaSenha123', $user->fresh()->password));
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/account/password', [
            'current_password' => 'senhaErrada',
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ]);

        $response->assertStatus(422);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_change_password_rejects_a_weak_new_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/account/password', [
            'current_password' => 'password',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ]);

        $response->assertStatus(422);
    }

    public function test_delete_account_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/account', ['password' => 'senhaErrada']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_delete_account_removes_the_user_and_cascades_related_data(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
            'balance' => 1,
            'price_usd' => 100,
            'captured_at' => now(),
        ]);

        $alert = AlertRule::create([
            'user_id' => $user->id,
            'type' => AlertRule::TYPE_PORTFOLIO_CHANGE,
            'threshold_percent' => 10,
            'direction' => 'down',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/account', ['password' => 'password']);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('wallets', ['id' => $wallet->id]);
        $this->assertDatabaseMissing('wallet_balance_histories', ['wallet_id' => $wallet->id]);
        $this->assertDatabaseMissing('alert_rules', ['id' => $alert->id]);
    }

    public function test_delete_account_revokes_all_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('api-token');
        $this->assertSame(1, $user->tokens()->count());

        Sanctum::actingAs($user);
        $this->deleteJson('/api/account', ['password' => 'password'])->assertStatus(200);

        $this->assertSame(0, $user->tokens()->count());
    }
}
