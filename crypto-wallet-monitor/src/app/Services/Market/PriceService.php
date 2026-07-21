<?php

namespace App\Services\Market;

use App\Services\Blockchain\BlockchainResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PriceService
{
    /**
     * Retorna preco atual (USD) e variacao de 24h de cada moeda suportada.
     *
     * Ex: ['ethereum' => ['usd' => 3245.67, 'change_24h' => 2.34], ...]
     *
     * Os IDs usados pela CoinGecko coincidem com as chaves de rede do
     * sistema ('ethereum', 'solana', 'bitcoin'), entao reaproveitamos
     * BlockchainResolver::supportedNetworks() em vez de duplicar a lista.
     */
    public function current(): array
    {
        return Cache::remember('coin_prices_usd', now()->addSeconds(60), function () {
            $networks = BlockchainResolver::supportedNetworks();

            $response = Http::get(config('market.coingecko.base_url') . '/simple/price', [
                'ids' => implode(',', $networks),
                'vs_currencies' => 'usd',
                'include_24hr_change' => 'true',
            ]);

            if (!$response->successful()) {
                abort(502, 'Erro ao consultar cotações');
            }

            $json = $response->json();
            $prices = [];

            foreach ($networks as $network) {
                if (!isset($json[$network]['usd'])) {
                    continue;
                }

                $prices[$network] = [
                    'usd' => $json[$network]['usd'],
                    'change_24h' => $json[$network]['usd_24h_change'] ?? 0,
                ];
            }

            return $prices;
        });
    }
}
