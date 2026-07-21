<?php

namespace App\Services\Blockchain;

use Illuminate\Support\Facades\Http;
use App\Services\Blockchain\Contracts\BlockchainServiceInterface;
use Illuminate\Support\Facades\Cache;

class BitcoinService implements BlockchainServiceInterface
{
    private const SATOSHIS_PER_BTC = 100_000_000;

    /**
     * Retorna o saldo de uma carteira Bitcoin em BTC.
     *
     * Diferente do Ethereum/Solana, um no Bitcoin nao expoe "saldo de um
     * endereco" via RPC simples (exigiria indexar todos os UTXOs). Por
     * isso usamos a API publica do Blockstream (Esplora), que ja mantem
     * esse indice pronto.
     */
    public function getBalance(string $address): float
    {
        $cacheKey = 'btc_balance:' . $address;

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($address) {

            $response = Http::get(
                config('blockchain.bitcoin.api_url') . "/address/{$address}"
            );

            if (!$response->successful()) {
                abort(502, 'Erro ao consultar a blockchain');
            }

            $json = $response->json();

            if (!isset($json['chain_stats']['funded_txo_sum'], $json['chain_stats']['spent_txo_sum'])) {
                abort(502, 'Resposta inválida da blockchain');
            }

            $satoshis = $json['chain_stats']['funded_txo_sum'] - $json['chain_stats']['spent_txo_sum'];

            return (float) bcdiv((string) $satoshis, (string) self::SATOSHIS_PER_BTC, 8);
        });
    }

    public function symbol(): string
    {
        return 'BTC';
    }

    public function addressPattern(): string
    {
        // Legacy (1...), P2SH (3...) e Bech32/SegWit nativo (bc1...)
        return '/^(1[a-km-zA-HJ-NP-Z1-9]{25,34}|3[a-km-zA-HJ-NP-Z1-9]{25,34}|bc1[a-z0-9]{25,62})$/';
    }
}
