<?php

namespace Tests\Feature;

use App\Jobs\RefreshWalletBalance;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CaptureWalletBalancesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_a_refresh_job_for_every_wallet(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $walletA = Wallet::factory()->for($user)->create();
        $walletB = Wallet::factory()->for($user)->create();

        $this->artisan('wallets:capture-balances')->assertSuccessful();

        Queue::assertPushed(RefreshWalletBalance::class, 2);
        Queue::assertPushed(fn (RefreshWalletBalance $job) => $job->walletId === $walletA->id);
        Queue::assertPushed(fn (RefreshWalletBalance $job) => $job->walletId === $walletB->id);
    }

    public function test_dispatches_nothing_when_there_are_no_wallets(): void
    {
        Queue::fake();

        $this->artisan('wallets:capture-balances')->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
