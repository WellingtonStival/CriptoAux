<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalanceHistory;
use App\Services\Alerts\AlertEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlertEvaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['telegram.bot_token' => 'fake-token', 'telegram.bot_username' => 'NexfolioTestBot']);
    }

    private function userWithTelegram(): User
    {
        return User::factory()->create(['telegram_chat_id' => '777']);
    }

    public function test_wallet_balance_drop_fires_when_threshold_is_exceeded(): void
    {
        $user = $this->userWithTelegram();
        $wallet = Wallet::factory()->for($user)->create(['name' => 'Carteira Principal']);

        AlertRule::create([
            'user_id' => $user->id,
            'type' => AlertRule::TYPE_WALLET_BALANCE_DROP,
            'wallet_id' => $wallet->id,
            'threshold_percent' => 10,
            'direction' => 'down',
        ]);

        Http::fake(['*sendMessage*' => Http::response(['ok' => true])]);

        app(AlertEvaluationService::class)->checkWalletBalanceDrop($wallet, 10.0, 8.5); // caiu 15%

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'Carteira Principal'));

        $this->assertNotNull(AlertRule::first()->last_triggered_at);
    }

    public function test_wallet_balance_drop_does_not_fire_below_threshold(): void
    {
        $user = $this->userWithTelegram();
        $wallet = Wallet::factory()->for($user)->create();

        AlertRule::create([
            'user_id' => $user->id,
            'type' => AlertRule::TYPE_WALLET_BALANCE_DROP,
            'wallet_id' => $wallet->id,
            'threshold_percent' => 20,
            'direction' => 'down',
        ]);

        Http::fake();

        app(AlertEvaluationService::class)->checkWalletBalanceDrop($wallet, 10.0, 9.5); // caiu so 5%

        Http::assertNothingSent();
    }

    public function test_wallet_balance_drop_ignores_a_balance_increase(): void
    {
        $user = $this->userWithTelegram();
        $wallet = Wallet::factory()->for($user)->create();

        AlertRule::create([
            'user_id' => $user->id,
            'type' => AlertRule::TYPE_WALLET_BALANCE_DROP,
            'wallet_id' => $wallet->id,
            'threshold_percent' => 5,
            'direction' => 'down',
        ]);

        Http::fake();

        app(AlertEvaluationService::class)->checkWalletBalanceDrop($wallet, 10.0, 12.0);

        Http::assertNothingSent();
    }

    public function test_wallet_balance_drop_rule_with_null_wallet_matches_any_wallet(): void
    {
        $user = $this->userWithTelegram();
        $wallet = Wallet::factory()->for($user)->create();

        AlertRule::create([
            'user_id' => $user->id,
            'type' => AlertRule::TYPE_WALLET_BALANCE_DROP,
            'wallet_id' => null,
            'threshold_percent' => 10,
            'direction' => 'down',
        ]);

        Http::fake(['*sendMessage*' => Http::response(['ok' => true])]);

        app(AlertEvaluationService::class)->checkWalletBalanceDrop($wallet, 10.0, 8.0);

        Http::assertSentCount(1);
    }

    public function test_wallet_balance_drop_respects_debounce(): void
    {
        $user = $this->userWithTelegram();
        $wallet = Wallet::factory()->for($user)->create();

        $rule = AlertRule::create([
            'user_id' => $user->id,
            'type' => AlertRule::TYPE_WALLET_BALANCE_DROP,
            'wallet_id' => $wallet->id,
            'threshold_percent' => 5,
            'direction' => 'down',
        ]);
        $rule->forceFill(['last_triggered_at' => now()->subHour()])->save();

        Http::fake();

        app(AlertEvaluationService::class)->checkWalletBalanceDrop($wallet, 10.0, 8.0);

        Http::assertNothingSent();
    }

    public function test_wallet_balance_drop_does_nothing_without_telegram_linked(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => null]);
        $wallet = Wallet::factory()->for($user)->create();

        AlertRule::create([
            'user_id' => $user->id,
            'type' => AlertRule::TYPE_WALLET_BALANCE_DROP,
            'wallet_id' => $wallet->id,
            'threshold_percent' => 5,
            'direction' => 'down',
        ]);

        Http::fake();

        app(AlertEvaluationService::class)->checkWalletBalanceDrop($wallet, 10.0, 8.0);

        Http::assertNothingSent();
    }

    public function test_portfolio_change_fires_on_a_drop(): void
    {
        $user = $this->userWithTelegram();
        $wallet = Wallet::factory()->for($user)->create(['network' => 'ethereum']);

        WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => 'ethereum',
            'balance' => 10,
            'price_usd' => 100,
            'captured_at' => now()->subHours(25),
        ]);

        WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => 'ethereum',
            'balance' => 10,
            'price_usd' => 80,
            'captured_at' => now(),
        ]);

        AlertRule::create([
            'user_id' => $user->id,
            'type' => AlertRule::TYPE_PORTFOLIO_CHANGE,
            'threshold_percent' => 10,
            'direction' => 'down',
        ]);

        Http::fake(['*sendMessage*' => Http::response(['ok' => true])]);

        app(AlertEvaluationService::class)->evaluatePeriodicRules();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'caiu'));
    }

    public function test_price_change_fires_using_the_coin_24h_change(): void
    {
        $user = $this->userWithTelegram();

        AlertRule::create([
            'user_id' => $user->id,
            'type' => AlertRule::TYPE_PRICE_CHANGE,
            'network' => 'bitcoin',
            'threshold_percent' => 5,
            'direction' => 'down',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'coins/markets')) {
                return Http::response([
                    [
                        'id' => 'bitcoin',
                        'current_price' => 60000,
                        'price_change_percentage_24h_in_currency' => -8.5,
                    ],
                ]);
            }

            return Http::response(['ok' => true]);
        });

        app(AlertEvaluationService::class)->evaluatePeriodicRules();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'Bitcoin'));
    }

    public function test_periodic_rules_skip_users_without_telegram_linked(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => null]);

        AlertRule::create([
            'user_id' => $user->id,
            'type' => AlertRule::TYPE_PRICE_CHANGE,
            'network' => 'bitcoin',
            'threshold_percent' => 1,
            'direction' => 'any',
        ]);

        Http::fake(['*coins/markets*' => Http::response([
            ['id' => 'bitcoin', 'current_price' => 60000, 'price_change_percentage_24h_in_currency' => -50],
        ])]);

        app(AlertEvaluationService::class)->evaluatePeriodicRules();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }
}
