<?php

namespace App\Services\Blockchain;

use Illuminate\Support\Facades\Http;
use App\Services\Blockchain\Contracts\BlockchainServiceInterface;
use App\Services\Blockchain\Contracts\TransactionHistoryProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BitcoinService implements BlockchainServiceInterface, TransactionHistoryProvider
{
    private const SATOSHIS_PER_BTC = 100_000_000;

    private function cacheKey(string $address): string
    {
        return 'btc_balance:' . $address;
    }

    public function getCachedBalance(string $address): ?float
    {
        return Cache::get($this->cacheKey($address));
    }

    /**
     * Retorna o saldo de uma carteira Bitcoin em BTC.
     *
     * Diferente do Ethereum/Solana, um no Bitcoin nao expoe "saldo de um
     * endereco" via RPC simples (exigiria indexar todos os UTXOs). Por
     * isso usamos a API publica do Blockstream (Esplora), que ja mantem
     * esse indice pronto. Com $forceRefresh, ignora um cache ainda
     * valido e busca ao vivo mesmo assim.
     */
    public function getBalance(string $address, bool $forceRefresh = false): float
    {
        $cacheKey = $this->cacheKey($address);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($address) {

            $response = Http::timeout(5)->retry(2, 200, throw: false)->get(
                config('blockchain.bitcoin.api_url') . "/address/{$address}"
            );

            if (!$response->successful()) {
                Log::warning('Falha ao consultar saldo Bitcoin', [
                    'address' => $address,
                    'status' => $response->status(),
                ]);
                abort(502, 'Erro ao consultar a blockchain');
            }

            $json = $response->json();

            if (!isset($json['chain_stats']['funded_txo_sum'], $json['chain_stats']['spent_txo_sum'])) {
                Log::warning('Resposta invalida da API Bitcoin', ['address' => $address]);
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

    /**
     * Lista as ultimas transacoes de um endereco, usando o mesmo indice da
     * Blockstream ja usado para o saldo. Direcao/valor sao calculados pela
     * diferenca entre o que entrou (vout) e o que saiu (vin) para o
     * endereco consultado, cobrindo corretamente o troco (change) de uma
     * transacao.
     */
    public function getTransactions(string $address, int $limit = 10): array
    {
        $cacheKey = "btc_txs:{$address}:{$limit}";

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($address, $limit) {
            $response = Http::timeout(5)->retry(2, 200, throw: false)->get(
                config('blockchain.bitcoin.api_url') . "/address/{$address}/txs"
            );

            if (!$response->successful()) {
                Log::warning('Falha ao consultar transacoes Bitcoin', [
                    'address' => $address,
                    'status' => $response->status(),
                ]);
                abort(502, 'Erro ao consultar transações na blockchain');
            }

            $txs = $response->json() ?? [];
            $transactions = [];

            foreach (array_slice($txs, 0, $limit) as $tx) {
                $received = 0;
                $sent = 0;

                foreach ($tx['vout'] ?? [] as $output) {
                    if (($output['scriptpubkey_address'] ?? null) === $address) {
                        $received += $output['value'] ?? 0;
                    }
                }

                foreach ($tx['vin'] ?? [] as $input) {
                    if (($input['prevout']['scriptpubkey_address'] ?? null) === $address) {
                        $sent += $input['prevout']['value'] ?? 0;
                    }
                }

                $netSatoshis = $received - $sent;
                $blockTime = $tx['status']['block_time'] ?? null;

                $transactions[] = [
                    'hash' => $tx['txid'],
                    'direction' => $netSatoshis >= 0 ? 'in' : 'out',
                    'amount' => (float) bcdiv((string) abs($netSatoshis), (string) self::SATOSHIS_PER_BTC, 8),
                    'timestamp' => $blockTime ? Carbon::createFromTimestamp($blockTime)->toIso8601String() : null,
                    'explorer_url' => "https://blockstream.info/tx/{$tx['txid']}",
                ];
            }

            return $transactions;
        });
    }
}
