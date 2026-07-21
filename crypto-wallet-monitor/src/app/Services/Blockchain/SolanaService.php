<?php

namespace App\Services\Blockchain;

use Illuminate\Support\Facades\Http;
use App\Services\Blockchain\Contracts\BlockchainServiceInterface;
use Illuminate\Support\Facades\Cache;

class SolanaService implements BlockchainServiceInterface
{
    private const LAMPORTS_PER_SOL = 1_000_000_000;

    /**
     * Retorna o saldo de uma carteira Solana em SOL
     */
    public function getBalance(string $address): float
    {
        $cacheKey = 'sol_balance:' . $address;

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($address) {

            $response = Http::post(
                config('blockchain.solana.rpc_url'),
                [
                    'jsonrpc' => '2.0',
                    'method'  => 'getBalance',
                    'params'  => [$address],
                    'id'      => 1,
                ]
            );

            if (!$response->successful()) {
                abort(502, 'Erro ao consultar a blockchain');
            }

            $json = $response->json();

            if (isset($json['error'])) {
                abort(502, 'Erro RPC Solana: ' . $json['error']['message']);
            }

            if (!isset($json['result']['value'])) {
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
}
