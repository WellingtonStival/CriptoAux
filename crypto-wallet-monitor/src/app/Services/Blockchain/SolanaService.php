<?php

namespace App\Services\Blockchain;

use Illuminate\Support\Facades\Http;
use App\Services\Blockchain\Contracts\BlockchainServiceInterface;
use App\Services\Blockchain\Contracts\TokenDiscoveryProvider;
use App\Services\Blockchain\Contracts\TransactionHistoryProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SolanaService implements BlockchainServiceInterface, TransactionHistoryProvider, TokenDiscoveryProvider
{
    private const LAMPORTS_PER_SOL = 1_000_000_000;

    /**
     * Program ID padrao do SPL Token na Solana - constante publica, igual
     * pra qualquer token que segue o padrao (nao e um contrato por token
     * como no Ethereum, e um "dono" comum de todas as contas de token).
     */
    private const SPL_TOKEN_PROGRAM_ID = 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA';

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

    /**
     * Descobre tokens SPL via getTokenAccountsByOwner - diferente do
     * Ethereum, essa e uma chamada normal da propria RPC (nao precisa de
     * indexador terceiro): cada token que uma conta possui na Solana tem
     * sua propria "conta de token", e essa chamada lista todas de uma vez.
     *
     * A RPC nao devolve simbolo/nome do token - resolvido a parte via
     * fetchTokenMetadata() (API publica da Jupiter, sem chave). Se essa
     * segunda chamada falhar, symbol/name ficam null e o frontend cai pro
     * endereco do token truncado como rotulo (mesmo padrao de fallback ja
     * usado pra rede desconhecida) - a lista de saldos nunca depende dela.
     */
    public function discoverTokens(string $address): array
    {
        $response = Http::timeout(10)->retry(2, 200, throw: false)->post(config('blockchain.solana.rpc_url'), [
            'jsonrpc' => '2.0',
            'method' => 'getTokenAccountsByOwner',
            'params' => [
                $address,
                ['programId' => self::SPL_TOKEN_PROGRAM_ID],
                ['encoding' => 'jsonParsed'],
            ],
            'id' => 1,
        ]);

        if (!$response->successful()) {
            Log::warning('Falha ao descobrir tokens Solana', [
                'address' => $address,
                'status' => $response->status(),
            ]);
            return [];
        }

        $accounts = $response->json('result.value') ?? [];
        $tokens = [];

        foreach ($accounts as $account) {
            $info = $account['account']['data']['parsed']['info'] ?? null;
            $amount = $info['tokenAmount'] ?? null;

            if (!$info || !$amount) {
                continue;
            }

            $uiAmount = (float) ($amount['uiAmount'] ?? 0);

            if ($uiAmount <= 0) {
                continue;
            }

            $tokens[] = [
                'contract_address' => $info['mint'],
                'symbol' => null,
                'name' => null,
                'logo_url' => null,
                'decimals' => (int) $amount['decimals'],
                'balance' => $uiAmount,
            ];
        }

        $metadata = $this->fetchTokenMetadata(array_column($tokens, 'contract_address'));

        foreach ($tokens as &$token) {
            $found = $metadata[$token['contract_address']] ?? null;
            $token['symbol'] = $found['symbol'] ?? null;
            $token['name'] = $found['name'] ?? null;
            $token['logo_url'] = $found['logo_url'] ?? null;
        }
        unset($token);

        return $tokens;
    }

    /**
     * Busca nome/simbolo de varios mints numa unica chamada em lote (a API
     * aceita ate 100 enderecos por requisicao - por isso o chunk).
     */
    private function fetchTokenMetadata(array $mintAddresses): array
    {
        $mintAddresses = array_values(array_unique($mintAddresses));

        if ($mintAddresses === []) {
            return [];
        }

        $metadata = [];

        foreach (array_chunk($mintAddresses, 100) as $chunk) {
            $response = Http::timeout(5)->retry(2, 200, throw: false)->get(config('blockchain.solana.token_metadata_url'), [
                'query' => implode(',', $chunk),
            ]);

            if (!$response->successful()) {
                Log::warning('Falha ao consultar nomes de tokens Solana (Jupiter)', [
                    'status' => $response->status(),
                ]);
                continue;
            }

            foreach ($response->json() ?? [] as $entry) {
                if (!isset($entry['id'])) {
                    continue;
                }

                $metadata[$entry['id']] = [
                    'symbol' => $entry['symbol'] ?? null,
                    'name' => $entry['name'] ?? null,
                    'logo_url' => $entry['icon'] ?? null,
                ];
            }
        }

        return $metadata;
    }

    /**
     * Atualiza o saldo de um token ja conhecido. Como uma conta pode ter
     * mais de uma "conta de token" pro mesmo mint (raro, mas possivel),
     * soma todas - mesma logica de "quanto desse token essa carteira tem
     * no total" que discoverTokens ja aplica implicitamente ao listar
     * cada conta separada.
     */
    public function getTokenBalance(string $address, string $contractAddress, int $decimals): float
    {
        $response = Http::timeout(5)->retry(2, 200, throw: false)->post(config('blockchain.solana.rpc_url'), [
            'jsonrpc' => '2.0',
            'method' => 'getTokenAccountsByOwner',
            'params' => [
                $address,
                ['mint' => $contractAddress],
                ['encoding' => 'jsonParsed'],
            ],
            'id' => 1,
        ]);

        if (!$response->successful()) {
            Log::warning('Falha ao consultar saldo de token Solana', [
                'address' => $address,
                'mint' => $contractAddress,
                'status' => $response->status(),
            ]);
            abort(502, 'Erro ao consultar saldo do token');
        }

        $accounts = $response->json('result.value') ?? [];
        $total = 0.0;

        foreach ($accounts as $account) {
            $amount = $account['account']['data']['parsed']['info']['tokenAmount'] ?? null;
            $total += (float) ($amount['uiAmount'] ?? 0);
        }

        return $total;
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
