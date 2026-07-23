<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Aproximacao propria do "Altcoin Season Index" popularizado pela
 * blockchaincenter.net/CoinMarketCap - nao existe API publica gratuita
 * pra esse indice (verificado antes de implementar), entao calculamos a
 * mesma ideia com dado que ja temos da CoinGecko.
 *
 * Diferenca importante de metodologia, deixada explicita na resposta pro
 * frontend: o indice "oficial" usa janela de 90 dias: pra fazer isso sem
 * gastar uma chamada por moeda (a CoinGecko nao tem "variacao em 90 dias"
 * pronta em /coins/markets, so 1h/24h/7d/14d/30d/200d/1y), usamos a
 * variacao de **30 dias** como proxy - mesma logica (% das top moedas que
 * bateram o Bitcoin no periodo), janela diferente. Por isso o numero pode
 * nao bater exatamente com o que a CoinMarketCap mostra.
 */
class AltcoinSeasonService
{
    private const TOP_N = 50;

    /**
     * Simbolos excluidos da comparacao - stablecoins (nao "competem" com o
     * Bitcoin em valorizacao por definicao) e derivativos/wrapped do
     * proprio BTC ou ETH (moveriam junto com o ativo original, enviesando
     * o indice). Mesma logica de exclusao usada pela blockchaincenter.
     */
    private const EXCLUDED_SYMBOLS = [
        'usdt', 'usdc', 'dai', 'busd', 'tusd', 'usdp', 'fdusd', 'usde', 'pyusd', 'usds',
        'wbtc', 'weth', 'steth', 'wsteth', 'weeth', 'cbbtc', 'wbeth', 'reth',
    ];

    /**
     * @return array{value: int, classification: string, methodology: string}|null
     */
    public function current(): ?array
    {
        return Cache::remember('altcoin_season', now()->addHour(), function () {
            $apiKey = config('market.coingecko.api_key');
            $request = Http::timeout(10)->retry(2, 200, throw: false);

            if ($apiKey) {
                $request = $request->withHeaders(['x-cg-demo-api-key' => $apiKey]);
            }

            $response = $request->get(config('market.coingecko.base_url') . '/coins/markets', [
                'vs_currency' => 'usd',
                'order' => 'market_cap_desc',
                'per_page' => 80,
                'page' => 1,
                'price_change_percentage' => '30d',
            ]);

            if (!$response->successful()) {
                Log::warning('Falha ao calcular Altcoin Season Index', ['status' => $response->status()]);
                return null;
            }

            $coins = collect($response->json() ?? []);
            $bitcoin = $coins->firstWhere('id', 'bitcoin');
            $btcChange30d = $bitcoin['price_change_percentage_30d_in_currency'] ?? null;

            if ($btcChange30d === null) {
                return null;
            }

            $altcoins = $coins
                ->reject(fn (array $coin) => $coin['id'] === 'bitcoin')
                ->reject(fn (array $coin) => in_array(strtolower($coin['symbol']), self::EXCLUDED_SYMBOLS, true))
                ->filter(fn (array $coin) => $coin['price_change_percentage_30d_in_currency'] !== null)
                ->take(self::TOP_N);

            if ($altcoins->isEmpty()) {
                return null;
            }

            $outperformed = $altcoins->filter(
                fn (array $coin) => $coin['price_change_percentage_30d_in_currency'] > $btcChange30d
            )->count();

            $value = (int) round(($outperformed / $altcoins->count()) * 100);

            return [
                'value' => $value,
                'classification' => match (true) {
                    $value >= 75 => 'Temporada de Altcoins',
                    $value <= 25 => 'Temporada de Bitcoin',
                    default => 'Misto',
                },
                'methodology' => 'Aproximação própria: % das top ' . $altcoins->count() . ' moedas (exceto stablecoins e wrapped) que subiram mais que o Bitcoin nos últimos 30 dias. O índice oficial usa janela de 90 dias.',
            ];
        });
    }
}
