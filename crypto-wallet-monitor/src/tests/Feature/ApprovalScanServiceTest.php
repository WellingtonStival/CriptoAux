<?php

namespace Tests\Feature;

use App\Services\Security\ApprovalScanService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApprovalScanServiceTest extends TestCase
{
    private const ADDRESS = '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045';

    private function goPlusResponse(array $approvedList, array $tokenOverrides = []): array
    {
        return [
            'code' => 1,
            'message' => 'ok',
            'result' => [
                array_merge([
                    'token_address' => '0xtoken',
                    'token_name' => 'Some Token',
                    'token_symbol' => 'TKN',
                    'malicious_address' => 0,
                    'malicious_behavior' => [],
                    'approved_list' => $approvedList,
                ], $tokenOverrides),
            ],
        ];
    }

    public function test_returns_empty_for_an_unsupported_network(): void
    {
        Http::fake();

        $result = app(ApprovalScanService::class)->scan('solana', self::ADDRESS);

        $this->assertSame([], $result);
        Http::assertNothingSent();
    }

    public function test_classifies_unlimited_approval_to_closed_source_contract_as_high_risk(): void
    {
        Http::fake(['*' => Http::response($this->goPlusResponse([
            [
                'approved_contract' => '0xspender',
                'approved_amount' => 'Unlimited',
                'approved_time' => 1700000000,
                'address_info' => ['contract_name' => 'Sketchy', 'is_open_source' => 0],
            ],
        ]))]);

        $result = app(ApprovalScanService::class)->scan('ethereum', self::ADDRESS);

        $this->assertCount(1, $result);
        $this->assertSame('alta', $result[0]['risk']);
        $this->assertTrue($result[0]['is_unlimited']);
        $this->assertFalse($result[0]['is_open_source']);
    }

    public function test_classifies_unlimited_approval_to_open_source_contract_as_medium_risk(): void
    {
        Http::fake(['*' => Http::response($this->goPlusResponse([
            [
                'approved_contract' => '0xspender',
                'approved_amount' => 'Unlimited',
                'address_info' => ['contract_name' => 'Uniswap Router', 'is_open_source' => 1],
            ],
        ]))]);

        $result = app(ApprovalScanService::class)->scan('ethereum', self::ADDRESS);

        $this->assertSame('media', $result[0]['risk']);
    }

    public function test_classifies_limited_approval_as_low_risk(): void
    {
        Http::fake(['*' => Http::response($this->goPlusResponse([
            [
                'approved_contract' => '0xspender',
                'approved_amount' => '1000000',
                'address_info' => ['contract_name' => 'Some Dapp', 'is_open_source' => 1],
            ],
        ]))]);

        $result = app(ApprovalScanService::class)->scan('ethereum', self::ADDRESS);

        $this->assertSame('baixa', $result[0]['risk']);
    }

    public function test_classifies_malicious_flagged_contract_as_high_risk_regardless_of_amount(): void
    {
        Http::fake(['*' => Http::response($this->goPlusResponse(
            [
                [
                    'approved_contract' => '0xspender',
                    'approved_amount' => '100',
                    'address_info' => ['contract_name' => 'Scam', 'is_open_source' => 1],
                ],
            ],
            ['malicious_address' => 1]
        ))]);

        $result = app(ApprovalScanService::class)->scan('ethereum', self::ADDRESS);

        $this->assertSame('alta', $result[0]['risk']);
    }

    public function test_caches_the_response(): void
    {
        Http::fake(['*' => Http::response($this->goPlusResponse([]))]);

        $service = app(ApprovalScanService::class);
        $service->scan('ethereum', self::ADDRESS);
        $service->scan('ethereum', self::ADDRESS);

        Http::assertSentCount(1);
    }

    public function test_degrades_gracefully_on_failure(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $result = app(ApprovalScanService::class)->scan('ethereum', self::ADDRESS);

        $this->assertSame([], $result);
    }
}
