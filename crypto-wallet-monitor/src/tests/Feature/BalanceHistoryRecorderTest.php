<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletBalanceHistory;
use App\Services\Wallet\BalanceHistoryRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BalanceHistoryRecorderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response([
                ['id' => 'ethereum', 'current_price' => 1000, 'price_change_percentage_24h_in_currency' => 1],
            ]),
        ]);
    }

    public function test_does_not_create_a_new_snapshot_within_the_debounce_window(): void
    {
        $wallet = Wallet::factory()->for(User::factory())->create();
        $recorder = app(BalanceHistoryRecorder::class);

        $first = $recorder->capture($wallet, 1.0);
        $second = $recorder->capture($wallet, 1.0);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, WalletBalanceHistory::where('wallet_id', $wallet->id)->count());
    }

    public function test_creates_a_new_snapshot_when_outside_the_debounce_window(): void
    {
        $wallet = Wallet::factory()->for(User::factory())->create();

        WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
            'balance' => 1.0,
            'price_usd' => 1000,
            'captured_at' => now()->subMinutes(10),
        ]);

        $result = app(BalanceHistoryRecorder::class)->capture($wallet, 1.0);

        $this->assertNotNull($result);
        $this->assertSame(2, WalletBalanceHistory::where('wallet_id', $wallet->id)->count());
    }
}
