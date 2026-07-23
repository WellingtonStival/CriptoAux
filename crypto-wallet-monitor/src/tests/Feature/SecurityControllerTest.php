<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_scan_approvals(): void
    {
        $this->getJson('/api/security/approvals')->assertStatus(401);
    }

    public function test_only_scans_evm_wallets(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->for($user)->create([
            'network' => 'bitcoin',
            'address' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
        ]);
        Sanctum::actingAs($user);

        Http::fake();

        $response = $this->getJson('/api/security/approvals');

        $response->assertStatus(200)->assertJsonPath('summary.scanned_wallets', 0);
        Http::assertNothingSent();
    }

    public function test_aggregates_and_sorts_approvals_by_risk(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['network' => 'ethereum']);
        Sanctum::actingAs($user);

        Http::fake(['*' => Http::response([
            'result' => [
                [
                    'token_address' => '0xtoken1',
                    'token_symbol' => 'LOW',
                    'malicious_address' => 0,
                    'approved_list' => [[
                        'approved_contract' => '0xspender1',
                        'approved_amount' => '100',
                        'address_info' => ['is_open_source' => 1],
                    ]],
                ],
                [
                    'token_address' => '0xtoken2',
                    'token_symbol' => 'HIGH',
                    'malicious_address' => 0,
                    'approved_list' => [[
                        'approved_contract' => '0xspender2',
                        'approved_amount' => 'Unlimited',
                        'address_info' => ['is_open_source' => 0],
                    ]],
                ],
            ],
        ])]);

        $response = $this->getJson('/api/security/approvals');

        $response->assertStatus(200);
        $this->assertSame('HIGH', $response->json('approvals.0.token_symbol'));
        $this->assertSame('alta', $response->json('approvals.0.risk'));
        $this->assertSame('LOW', $response->json('approvals.1.token_symbol'));
        $this->assertSame($wallet->id, $response->json('approvals.0.wallet_id'));
        $this->assertSame(1, $response->json('summary.high_risk'));
        $this->assertSame(2, $response->json('summary.total'));
        $this->assertSame(1, $response->json('summary.scanned_wallets'));
    }

    public function test_does_not_scan_another_users_wallets(): void
    {
        $otherUser = User::factory()->create();
        Wallet::factory()->for($otherUser)->create(['network' => 'ethereum']);

        Sanctum::actingAs(User::factory()->create());
        Http::fake();

        $response = $this->getJson('/api/security/approvals');

        $response->assertJsonPath('summary.scanned_wallets', 0);
        Http::assertNothingSent();
    }
}
