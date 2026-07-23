<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dados globais de mercado (dominancia por moeda, market cap total,
 * variacao 24h) via /global da CoinGecko - mesmo fornecedor ja usado pra
 * preco, endpoint publico que funciona mesmo sem API key (embora a chave,
 * se configurada, tambem seja enviada - sem custo extra, mesmo padrao do
 * PriceService).
 */
class GlobalMarketService
{
    /**
     * @return array{btc_dominance: float, eth_dominance: float, total_market_cap_usd: float, market_cap_change_24h: float}|null
     */
    public function current(): ?array
    {
        return Cache::remember('global_market', now()->addMinutes(15), function () {
            $apiKey = config('market.coingecko.api_key');
            $request = Http::timeout(5)->retry(2, 200, throw: false);

            if ($apiKey) {
                $request = $request->withHeaders(['x-cg-demo-api-key' => $apiKey]);
            }

            $response = $request->get(config('market.coingecko.base_url') . '/global');

            if (!$response->successful()) {
                Log::warning('Falha ao consultar dados globais CoinGecko', ['status' => $response->status()]);
                return null;
            }

            $data = $response->json('data');

            if (!$data) {
                return null;
            }

            return [
                'btc_dominance' => round($data['market_cap_percentage']['btc'] ?? 0, 1),
                'eth_dominance' => round($data['market_cap_percentage']['eth'] ?? 0, 1),
                'total_market_cap_usd' => $data['total_market_cap']['usd'] ?? 0,
                'market_cap_change_24h' => round($data['market_cap_change_percentage_24h_usd'] ?? 0, 2),
            ];
        });
    }
}
