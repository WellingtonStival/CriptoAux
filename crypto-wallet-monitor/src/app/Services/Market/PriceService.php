<?php

namespace App\Services\Market;

use App\Services\Blockchain\BlockchainResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PriceService
{
    /**
     * Retorna dados de mercado (preco, variacoes, volume, market cap,
     * maxima/minima 24h) de cada moeda suportada.
     *
     * Ex: ['ethereum' => ['usd' => 3245.67, 'change_24h' => 2.34, ...], ...]
     *
     * Os IDs usados pela CoinGecko coincidem com as chaves de rede do
     * sistema ('ethereum', 'solana', 'bitcoin'), entao reaproveitamos
     * BlockchainResolver::supportedNetworks() em vez de duplicar a lista.
     *
     * Usa /coins/markets em vez de /simple/price porque este ultimo so
     * traz preco + variacao 24h; o endpoint de markets traz tambem
     * volume, market cap, maxima/minima 24h e variacao de outros
     * periodos (7d/30d) numa unica chamada.
     */
    public function current(): array
    {
        return Cache::remember('coin_prices_usd', now()->addSeconds(60), function () {
            $networks = BlockchainResolver::supportedNetworks();

            $response = Http::get(config('market.coingecko.base_url') . '/coins/markets', [
                'vs_currency' => 'usd',
                'ids' => implode(',', $networks),
                'price_change_percentage' => '24h,7d,30d',
            ]);

            if (!$response->successful()) {
                abort(502, 'Erro ao consultar cotações');
            }

            $coins = $response->json();
            $prices = [];

            foreach ($coins as $coin) {
                $id = $coin['id'] ?? null;

                if (!in_array($id, $networks, true)) {
                    continue;
                }

                $prices[$id] = [
                    'usd' => $coin['current_price'] ?? null,
                    'change_24h' => $coin['price_change_percentage_24h_in_currency']
                        ?? $coin['price_change_percentage_24h']
                        ?? null,
                    'change_7d' => $coin['price_change_percentage_7d_in_currency'] ?? null,
                    'change_30d' => $coin['price_change_percentage_30d_in_currency'] ?? null,
                    'market_cap' => $coin['market_cap'] ?? null,
                    'volume_24h' => $coin['total_volume'] ?? null,
                    'high_24h' => $coin['high_24h'] ?? null,
                    'low_24h' => $coin['low_24h'] ?? null,
                ];
            }

            return $prices;
        });
    }
}
