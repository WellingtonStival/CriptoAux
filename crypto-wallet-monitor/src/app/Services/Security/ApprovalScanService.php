<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Busca aprovacoes de token (ERC-20 approve/allowance) concedidas por uma
 * wallet, via GoPlus Security - API publica e gratuita, sem chave
 * (verificado ao vivo em 2026-07-23 com uma wallet real, retornou dado
 * de verdade incluindo aprovacoes "Unlimited"). So EVM (Ethereum/
 * Polygon/BNB/Avalanche/Arbitrum) - Solana usa um mecanismo de
 * "delegate" diferente, sem suporte aqui ainda.
 */
class ApprovalScanService
{
    private const CHAIN_IDS = [
        'ethereum' => '1',
        'polygon' => '137',
        'bnb' => '56',
        'avalanche' => '43114',
        'arbitrum' => '42161',
    ];

    public function supportsNetwork(string $network): bool
    {
        return array_key_exists($network, self::CHAIN_IDS);
    }

    /**
     * @return array<int, array>
     */
    public function scan(string $network, string $address): array
    {
        $chainId = self::CHAIN_IDS[$network] ?? null;

        if (!$chainId) {
            return [];
        }

        $cacheKey = 'approval_scan:' . $network . ':' . strtolower($address);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($chainId, $network, $address) {
            $response = Http::timeout(15)->retry(2, 300, throw: false)
                ->get(config('security.goplus.base_url') . "/token_approval_security/{$chainId}", [
                    'addresses' => $address,
                ]);

            if (!$response->successful()) {
                Log::warning('Falha ao consultar aprovacoes GoPlus', [
                    'network' => $network,
                    'status' => $response->status(),
                ]);
                return [];
            }

            $tokens = $response->json('result') ?? [];
            $approvals = [];

            foreach ($tokens as $token) {
                foreach ($token['approved_list'] ?? [] as $approval) {
                    $approvals[] = $this->normalize($network, $token, $approval);
                }
            }

            return $approvals;
        });
    }

    private function normalize(string $network, array $token, array $approval): array
    {
        $addressInfo = $approval['address_info'] ?? [];
        $isUnlimited = ($approval['approved_amount'] ?? null) === 'Unlimited';
        $isOpenSource = (int) ($addressInfo['is_open_source'] ?? 1) === 1;
        $isMalicious = (int) ($token['malicious_address'] ?? 0) === 1
            || !empty($token['malicious_behavior'])
            || !empty($addressInfo['malicious_behavior'])
            || (int) ($addressInfo['doubt_list'] ?? 0) === 1;

        $risk = match (true) {
            $isMalicious => 'alta',
            $isUnlimited && !$isOpenSource => 'alta',
            $isUnlimited => 'media',
            default => 'baixa',
        };

        return [
            'network' => $network,
            'token_symbol' => $token['token_symbol'] ?? null,
            'token_name' => $token['token_name'] ?? null,
            'token_address' => $token['token_address'] ?? null,
            'spender_address' => $approval['approved_contract'] ?? null,
            'spender_name' => $addressInfo['contract_name'] ?? null,
            'is_unlimited' => $isUnlimited,
            'approved_amount' => $approval['approved_amount'] ?? null,
            'is_open_source' => $isOpenSource,
            'is_malicious' => $isMalicious,
            'approved_at' => isset($approval['approved_time']) ? (int) $approval['approved_time'] : null,
            'risk' => $risk,
        ];
    }
}
