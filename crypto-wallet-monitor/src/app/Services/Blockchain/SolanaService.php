<?php

namespace App\Services\Blockchain;

use Illuminate\Support\Facades\Http;
use App\Services\Blockchain\Contracts\BlockchainServiceInterface;
use App\Services\Blockchain\Contracts\TransactionHistoryProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SolanaService implements BlockchainServiceInterface, TransactionHistoryProvider
{
    private const LAMPORTS_PER_SOL = 1_000_000_000;

    private function cacheKey(string $address): string
    {
        return 'sol_balance:' . $address;
    }

    public function getCachedBalance(string $address): ?float
    {
        return Cache::get($this->cacheKey($address));
    }

    /**
     * Retorna o saldo de uma carteira Solana em SOL. Com $forceRefresh,
     * ignora um cache ainda valido e busca ao vivo mesmo assim.
     */
    public function getBalance(string $address, bool $forceRefresh = false): float
    {
        $cacheKey = $this->cacheKey($address);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($address) {

            $response = Http::timeout(5)->retry(2, 200, throw: false)->post(
                config('blockchain.solana.rpc_url'),
                [
                    'jsonrpc' => '2.0',
                    'method'  => 'getBalance',
                    'params'  => [$address],
                    'id'      => 1,
                ]
            );

            if (!$response->successful()) {
                Log::warning('Falha ao consultar saldo Solana', [
                    'address' => $address,
                    'status' => $response->status(),
                ]);
                abort(502, 'Erro ao consultar a blockchain');
            }

            $json = $response->json();

            if (isset($json['error'])) {
                Log::warning('Erro RPC Solana', ['address' => $address, 'error' => $json['error']]);
                abort(502, 'Erro RPC Solana: ' . $json['error']['message']);
            }

            if (!isset($json['result']['value'])) {
                Log::warning('Resposta invalida da RPC Solana', ['address' => $address]);
                abort(502, 'Resposta inválida da blockchain');
            }

            $lamports = $json['result']['value'];

            // Lamports -> SOL, com precisao usando BCMath (mesma logica do EthereumService)
            return (float) bcdiv((string) $lamports, (string) self::LAMPORTS_PER_SOL, 9);
        });
    }

    public function symbol(): string
    {
        return 'SOL';
    }

    public function addressPattern(): string
    {
        // Endereco Solana = chave publica Ed25519 em Base58 (32 bytes -> 32 a 44 caracteres)
        return '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/';
    }

    /**
     * Lista as ultimas transacoes de um endereco.
     *
     * A RPC Solana nao retorna valor/direcao em getSignaturesForAddress,
     * so a assinatura e o horario. Por isso, para cada assinatura, fazemos
     * uma segunda chamada (getTransaction) e calculamos a variacao de
     * saldo da propria conta (preBalance -> postBalance) para descobrir
     * quanto entrou ou saiu.
     */
    public function getTransactions(string $address, int $limit = 10): array
    {
        $cacheKey = "sol_txs:{$address}:{$limit}";

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($address, $limit) {
            $signaturesResponse = Http::timeout(5)->retry(2, 200, throw: false)->post(config('blockchain.solana.rpc_url'), [
                'jsonrpc' => '2.0',
                'method' => 'getSignaturesForAddress',
                'params' => [$address, ['limit' => $limit]],
                'id' => 1,
            ]);

            if (!$signaturesResponse->successful()) {
                Log::warning('Falha ao consultar transacoes Solana', [
                    'address' => $address,
                    'status' => $signaturesResponse->status(),
                ]);
                abort(502, 'Erro ao consultar transações na blockchain');
            }

            $signatures = $signaturesResponse->json('result') ?? [];
            $transactions = [];

            foreach ($signatures as $entry) {
                $signature = $entry['signature'] ?? null;

                if (!$signature) {
                    continue;
                }

                $transaction = $this->fetchTransactionDelta($address, $signature);

                if ($transaction === null) {
                    continue;
                }

                $transactions[] = [
                    'hash' => $signature,
                    'direction' => $transaction['direction'],
                    'amount' => $transaction['amount'],
                    'timestamp' => isset($entry['blockTime'])
                        ? Carbon::createFromTimestamp($entry['blockTime'])->toIso8601String()
                        : null,
                    'explorer_url' => "https://solscan.io/tx/{$signature}",
                ];
            }

            return $transactions;
        });
    }

    private function fetchTransactionDelta(string $address, string $signature): ?array
    {
        $response = Http::timeout(5)->retry(2, 200, throw: false)->post(config('blockchain.solana.rpc_url'), [
            'jsonrpc' => '2.0',
            'method' => 'getTransaction',
            'params' => [$signature, ['maxSupportedTransactionVersion' => 0]],
            'id' => 1,
        ]);

        if (!$response->successful()) {
            return null;
        }

        $tx = $response->json('result');
        $accountKeys = $tx['transaction']['message']['accountKeys'] ?? [];
        $index = array_search($address, $accountKeys, true);

        if ($index === false) {
            return null;
        }

        $pre = $tx['meta']['preBalances'][$index] ?? null;
        $post = $tx['meta']['postBalances'][$index] ?? null;

        if ($pre === null || $post === null) {
            return null;
        }

        $deltaLamports = $post - $pre;

        return [
            'direction' => $deltaLamports >= 0 ? 'in' : 'out',
            'amount' => (float) bcdiv((string) abs($deltaLamports), (string) self::LAMPORTS_PER_SOL, 9),
        ];
    }
}
