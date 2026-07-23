<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AlertControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_alerts(): void
    {
        $this->getJson('/api/alerts')->assertStatus(401);
    }

    public function test_user_can_create_a_wallet_balance_drop_alert(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['name' => 'Minha Carteira']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/alerts', [
            'type' => AlertRule::TYPE_WALLET_BALANCE_DROP,
            'wallet_id' => $wallet->id,
            'threshold_percent' => 10,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('type', AlertRule::TYPE_WALLET_BALANCE_DROP)
            ->assertJsonPath('wallet_label', 'Minha Carteira')
            ->assertJsonPath('is_active', true)
            ->assertJsonPath('direction', 'down');

        $this->assertDatabaseHas('alert_rules', [
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => AlertRule::TYPE_WALLET_BALANCE_DROP,
        ]);
    }

    public function test_user_can_create_a_price_change_alert(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/alerts', [
            'type' => AlertRule::TYPE_PRICE_CHANGE,
            'network' => 'bitcoin',
            'threshold_percent' => 5,
            'direction' => 'any',
        ]);

        $response->assertStatus(201)->assertJsonPath('network', 'bitcoin');
    }

    public function test_price_change_alert_requires_a_network(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/alerts', [
            'type' => AlertRule::TYPE_PRICE_CHANGE,
            'threshold_percent' => 5,
        ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_attach_another_users_wallet_to_an_alert(): void
    {
        $owner = User::factory()->create();
        $otherWallet = Wallet::factory()->for($owner)->create();

        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/alerts', [
            'type' => AlertRule::TYPE_WALLET_BALANCE_DROP,
            'wallet_id' => $otherWallet->id,
            'threshold_percent' => 10,
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_list_their_own_alerts(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        AlertRule::create([
            'user_id' => $user->id,
            'type' => AlertRule::TYPE_WALLET_BALANCE_DROP,
            'wallet_id' => $wallet->id,
            'threshold_percent' => 10,
            'direction' => 'down',
        ]);

        AlertRule::create([
            'user_id' => User::factory()->create()->id,
            'type' => AlertRule::TYPE_PORTFOLIO_CHANGE,
            'threshold_percent' => 10,
            'direction' => 'down',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/alerts');

        $response->assertStatus(200)->assertJsonCount(1, 'alerts');
    }

    public function test_user_can_toggle_an_alert_off(): void
    {
        $user = User::factory()->create();
        $rule = AlertRule::create([
            'user_id' => $user->id,
            'type' => AlertRule::TYPE_PORTFOLIO_CHANGE,
            'threshold_percent' => 10,
            'direction' => 'down',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/alerts/{$rule->id}", ['is_active' => false])
            ->assertStatus(200)
            ->assertJsonPath('is_active', false);
    }

    public function test_user_cannot_update_another_users_alert(): void
    {
        $rule = AlertRule::create([
            'user_id' => User::factory()->create()->id,
            'type' => AlertRule::TYPE_PORTFOLIO_CHANGE,
            'threshold_percent' => 10,
            'direction' => 'down',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/alerts/{$rule->id}", ['is_active' => false])->assertStatus(404);
    }

    public function test_user_can_delete_their_own_alert(): void
    {
        $user = User::factory()->create();
        $rule = AlertRule::create([
            'user_id' => $user->id,
            'type' => AlertRule::TYPE_PORTFOLIO_CHANGE,
            'threshold_percent' => 10,
            'direction' => 'down',
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/alerts/{$rule->id}")->assertStatus(200);

        $this->assertDatabaseMissing('alert_rules', ['id' => $rule->id]);
    }
}
