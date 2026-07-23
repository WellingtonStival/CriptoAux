<?php

namespace App\Services\Market;

use App\Services\Blockchain\BlockchainResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceService
{
    /**
     * Nossa chave de rede interna nem sempre bate com o "coin id" que a
     * CoinGecko usa em /coins/markets - por acaso bate pra
     * ethereum/solana/bitcoin, mas nao pra Polygon (o token nativo virou
     * "polygon-ecosystem-token" na migracao de MATIC pra POL) nem pra BNB
     * Chain (o coin id e "binancecoin", nao "bnb"). Mapeado explicitamente
     * em vez de assumir que sempre bate.
     */
    private const COINGECKO_COIN_IDS = [
        'ethereum' => 'ethereum',
        'polygon' => 'polygon-ecosystem-token',
        'bnb' => 'binancecoin',
        'solana' => 'solana',
        'bitcoin' => 'bitcoin',
    ];

    /**
     * Mesma ideia, mas pro "asset platform id" usado em
     * /simple/token_price/{platform} - tambem nao bate com o coin id pra
     * Polygon ("polygon-pos") nem BNB Chain ("binance-smart-chain").
     * Bitcoin nao entra aqui porque nao suporta tokens.
     */
    private const COINGECKO_PLATFORM_IDS = [
        'ethereum' => 'ethereum',
        'polygon' => 'polygon-pos',
        'bnb' => 'binance-smart-chain',
        'solana' => 'solana',
    ];

    /**
     * Retorna dados de mercado (preco, variacoes, volume, market cap,
     * maxima/minima 24h) de cada moeda suportada.
     *
     * Ex: ['ethereum' => ['usd' => 3245.67, 'change_24h' => 2.34, ...], ...]
     *
     * Usa /coins/markets em vez de /simple/price porque este ultimo so
     * traz preco + variacao 24h; o endpoint de markets traz tambem
     * volume, market cap, maxima/minima 24h e variacao de outros
     * periodos (7d/30d) numa unica chamada.
     */
    private function http(int $timeoutSeconds): PendingRequest
    {
        $request = Http::timeout($timeoutSeconds)->retry(2, 200, throw: false);
        $apiKey = config('market.coingecko.api_key');

        return $apiKey ? $request->withHeaders(['x-cg-demo-api-key' => $apiKey]) : $request;
    }

    public function current(): array
    {
        return Cache::remember('coin_prices_usd', now()->addSeconds(60), function () {
            $networks = BlockchainResolver::supportedNetworks();
            $coinIds = array_map(fn (string $network) => self::COINGECKO_COIN_IDS[$network] ?? $network, $networks);

            $response = $this->http(5)->get(config('market.coingecko.base_url') . '/coins/markets', [
                'vs_currency' => 'usd',
                'ids' => implode(',', $coinIds),
                'price_change_percentage' => '24h,7d,30d',
            ]);

            if (!$response->successful()) {
                Log::warning('Falha ao consultar cotacoes CoinGecko', ['status' => $response->status()]);
                abort(502, 'Erro ao consultar cotações');
            }

            $coins = $response->json();
            $prices = [];

            foreach ($coins as $coin) {
                $coinId = $coin['id'] ?? null;
                $network = array_search($coinId, self::COINGECKO_COIN_IDS, true);

                if ($network === false) {
                    continue;
                }

                $prices[$network] = [
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

    /**
     * Sem chave de API, a CoinGecko limita esse endpoint a **1 endereco de
     * contrato por requisicao** (confirmado ao vivo em 2026-07-23 - a
     * partir do segundo endereco ja responde 400). Com a chave gratuita
     * do plano Demo, o limite documentado sobe pra 515; usamos 100 por
     * chamada aqui, com folga. Sem chave configurada, cai pra 1 por vez -
     * funciona, mas fica bem mais lento e sujeito a rate limit com muitos
     * tokens (degradacao aceita, nao quebra a tela).
     */
    private const TOKEN_PRICE_CHUNK_SIZE_WITH_KEY = 100;
    private const TOKEN_PRICE_CHUNK_SIZE_WITHOUT_KEY = 1;

    /**
     * Preco de tokens por endereco de contrato - diferente de current(),
     * que so cobre as moedas nativas fixas do sistema. Mesmo fornecedor
     * (CoinGecko), endpoint proprio pra isso, sem precisar de outro
     * vendor so pra preco de token.
     *
     * $network e a chave de rede do sistema ('ethereum', 'polygon', ...) -
     * convertida aqui pro "id de plataforma" que a CoinGecko realmente usa
     * nesse endpoint (ver COINGECKO_PLATFORM_IDS).
     *
     * @return array<string, float> endereco do contrato (minusculo) => preco USD
     */
    public function tokenPrices(string $network, array $contractAddresses): array
    {
        $contractAddresses = array_values(array_unique(array_filter($contractAddresses)));

        if (empty($contractAddresses)) {
            return [];
        }

        $platformId = self::COINGECKO_PLATFORM_IDS[$network] ?? $network;
        $chunkSize = config('market.coingecko.api_key')
            ? self::TOKEN_PRICE_CHUNK_SIZE_WITH_KEY
            : self::TOKEN_PRICE_CHUNK_SIZE_WITHOUT_KEY;

        $prices = [];

        foreach (array_chunk($contractAddresses, $chunkSize) as $chunk) {
            $prices = array_merge($prices, $this->tokenPricesChunk($platformId, $network, $chunk));
        }

        return $prices;
    }

    /**
     * @param array<int, string> $contractAddresses
     * @return array<string, float>
     */
    private function tokenPricesChunk(string $platformId, string $network, array $contractAddresses): array
    {
        $cacheKey = 'token_prices_usd:' . $network . ':' . md5(implode(',', $contractAddresses));

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($platformId, $network, $contractAddresses) {
            $response = $this->http(10)->get(config('market.coingecko.base_url') . "/simple/token_price/{$platformId}", [
                'contract_addresses' => implode(',', $contractAddresses),
                'vs_currencies' => 'usd',
            ]);

            if (!$response->successful()) {
                Log::warning('Falha ao consultar cotacoes de token CoinGecko', [
                    'network' => $network,
                    'status' => $response->status(),
                ]);
                return [];
            }

            $prices = [];

            foreach ($response->json() ?? [] as $address => $data) {
                if (isset($data['usd'])) {
                    $prices[strtolower($address)] = (float) $data['usd'];
                }
            }

            return $prices;
        });
    }
}
