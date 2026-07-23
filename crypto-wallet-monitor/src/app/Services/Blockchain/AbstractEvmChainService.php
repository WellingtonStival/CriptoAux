<?php

namespace App\Services\Blockchain;

use App\Services\Blockchain\Contracts\BlockchainServiceInterface;
use App\Services\Blockchain\Contracts\TokenDiscoveryProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Logica compartilhada por qualquer rede compativel com EVM (mesma RPC
 * JSON, mesmo padrao de endereco 0x..., mesma Token API da Alchemy pra
 * descoberta de tokens). Ethereum, Polygon e BNB Chain sao praticamente
 * identicas nesse nivel - so muda a URL da RPC, o subdominio da Alchemy e
 * o simbolo nativo, por isso cada rede e uma subclasse fina que so
 * informa esses tres dados.
 */
abstract class AbstractEvmChainService implements BlockchainServiceInterface, TokenDiscoveryProvider
{
    /**
     * Alguma redes (ex: Polygon) listam o proprio saldo nativo como se
     * fosse mais um "token" dentro de alchemy_getTokenBalances, usando
     * esse endereco pseudo-contrato reservado. Nao e um ERC-20 de
     * verdade - ja aparece via getBalance(), entao e ignorado aqui pra
     * nao duplicar.
     */
    private const NATIVE_PSEUDO_CONTRACT = '0x0000000000000000000000000000000000001010';

    /**
     * Chave de rede usada em config('blockchain.{network}.*') e
     * config('alchemy.base_urls.{network}').
     */
    abstract protected function network(): string;

    private function cacheKey(string $address): string
    {
        // Prefixo baseado no simbolo (ex: "eth_balance:"), nao na chave de
        // rede - mantem compatibilidade com o cache key que o
        // EthereumService original ja usava antes desse refactor.
        return strtolower($this->symbol()) . '_balance:' . strtolower($address);
    }

    public function getCachedBalance(string $address): ?float
    {
        return Cache::get($this->cacheKey($address));
    }

    /**
     * Retorna o saldo de uma carteira em moeda nativa. Com $forceRefresh,
     * ignora um cache ainda valido e busca ao vivo mesmo assim (usado
     * pelo botao manual de atualizar saldo).
     */
    public function getBalance(string $address, bool $forceRefresh = false): float
    {
        $cacheKey = $this->cacheKey($address);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($address) {
            $response = Http::timeout(5)->retry(2, 200, throw: false)->post(
                config("blockchain.{$this->network()}.rpc_url"),
                [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_getBalance',
                    'params' => [$address, 'latest'],
                    'id' => 1,
                ]
            );

            if (!$response->successful()) {
                Log::warning("Falha ao consultar saldo {$this->symbol()}", [
                    'address' => $address,
                    'status' => $response->status(),
                ]);
                abort(502, 'Erro ao consultar a blockchain');
            }

            $json = $response->json();

            if (isset($json['error'])) {
                Log::warning("Erro RPC {$this->symbol()}", ['address' => $address, 'error' => $json['error']]);
                abort(502, "Erro RPC {$this->symbol()}: " . $json['error']['message']);
            }

            if (!isset($json['result'])) {
                Log::warning("Resposta invalida da RPC {$this->symbol()}", ['address' => $address]);
                abort(502, 'Resposta inválida da blockchain');
            }

            $weiHex = str_replace('0x', '', $json['result']);

            if (function_exists('gmp_init')) {
                $wei = gmp_strval(gmp_init($weiHex, 16), 10);
                return (float) bcdiv($wei, bcpow('10', '18'), 8);
            }

            return hexdec('0x' . $weiHex) / pow(10, 18);
        });
    }

    public function addressPattern(): string
    {
        return '/^0x[a-fA-F0-9]{40}$/';
    }

    /**
     * Descobre tokens ERC-20 via Alchemy Token API (alchemy_getTokenBalances
     * em modo "erc20" = descoberta automatica). A RPC comum nao tem como
     * listar "todos os tokens que este endereco possui" - so consegue
     * responder o saldo de UM contrato ja conhecido. Por isso essa parte
     * usa a Alchemy, nao a RPC configurada em blockchain.{network}.rpc_url.
     *
     * Sem ALCHEMY_API_KEY configurada, ou se a rede nao estiver habilitada
     * pro app na Alchemy, retorna lista vazia (degrada sem quebrar).
     */
    public function discoverTokens(string $address): array
    {
        $apiKey = config('alchemy.api_key');

        if (!$apiKey) {
            return [];
        }

        $url = $this->alchemyUrl($apiKey);

        $response = Http::timeout(10)->retry(2, 200, throw: false)->post($url, [
            'jsonrpc' => '2.0',
            'method' => 'alchemy_getTokenBalances',
            'params' => [$address, 'erc20'],
            'id' => 1,
        ]);

        if (!$response->successful()) {
            Log::warning("Falha ao descobrir tokens {$this->symbol()}", [
                'address' => $address,
                'status' => $response->status(),
            ]);
            return [];
        }

        $entries = $response->json('result.tokenBalances') ?? [];
        $tokens = [];

        foreach ($entries as $entry) {
            $contractAddress = $entry['contractAddress'] ?? null;
            $balanceHex = $entry['tokenBalance'] ?? null;

            if (!$contractAddress || !$balanceHex) {
                continue;
            }

            if (strtolower($contractAddress) === self::NATIVE_PSEUDO_CONTRACT) {
                continue;
            }

            $rawBalance = $this->hexToDecimalString($balanceHex);

            if ($rawBalance === '0') {
                continue;
            }

            $metadata = $this->fetchTokenMetadata($url, $contractAddress);
            $decimals = $metadata['decimals'] ?? 18;

            $tokens[] = [
                'contract_address' => $contractAddress,
                'symbol' => $metadata['symbol'] ?? null,
                'name' => $metadata['name'] ?? null,
                'logo_url' => $metadata['logo'] ?? null,
                'decimals' => $decimals,
                'balance' => (float) bcdiv($rawBalance, bcpow('10', (string) $decimals), 8),
            ];
        }

        return $tokens;
    }

    /**
     * Atualiza o saldo de um token ja conhecido via eth_call (funcao
     * balanceOf do padrao ERC-20), na RPC normal - nao gasta cota da
     * Alchemy pra isso, so a descoberta inicial usa a Alchemy.
     */
    public function getTokenBalance(string $address, string $contractAddress, int $decimals): float
    {
        // balanceOf(address) = seletor 0x70a08231 + endereco com padding pra 32 bytes
        $data = '0x70a08231000000000000000000000000' . substr(strtolower($address), 2);

        $response = Http::timeout(5)->retry(2, 200, throw: false)->post(
            config("blockchain.{$this->network()}.rpc_url"),
            [
                'jsonrpc' => '2.0',
                'method' => 'eth_call',
                'params' => [
                    ['to' => $contractAddress, 'data' => $data],
                    'latest',
                ],
                'id' => 1,
            ]
        );

        if (!$response->successful()) {
            Log::warning("Falha ao consultar saldo de token {$this->symbol()}", [
                'address' => $address,
                'contract' => $contractAddress,
                'status' => $response->status(),
            ]);
            abort(502, 'Erro ao consultar saldo do token');
        }

        $json = $response->json();

        if (isset($json['error']) || !isset($json['result'])) {
            Log::warning("Erro RPC ao consultar saldo de token {$this->symbol()}", [
                'address' => $address,
                'contract' => $contractAddress,
            ]);
            abort(502, 'Erro ao consultar saldo do token');
        }

        $rawBalance = $this->hexToDecimalString($json['result']);

        return (float) bcdiv($rawBalance, bcpow('10', (string) $decimals), 8);
    }

    private function alchemyUrl(string $apiKey): string
    {
        $base = config("alchemy.base_urls.{$this->network()}");

        return rtrim($base, '/') . '/' . $apiKey;
    }

    private function fetchTokenMetadata(string $alchemyUrl, string $contractAddress): array
    {
        $response = Http::timeout(5)->retry(2, 200, throw: false)->post($alchemyUrl, [
            'jsonrpc' => '2.0',
            'method' => 'alchemy_getTokenMetadata',
            'params' => [$contractAddress],
            'id' => 1,
        ]);

        if (!$response->successful()) {
            return [];
        }

        return $response->json('result') ?? [];
    }

    private function hexToDecimalString(string $hex): string
    {
        $hex = str_replace('0x', '', $hex);
        $hex = $hex === '' ? '0' : ltrim($hex, '0');
        $hex = $hex === '' ? '0' : $hex;

        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($hex, 16), 10);
        }

        return (string) hexdec($hex);
    }
}
