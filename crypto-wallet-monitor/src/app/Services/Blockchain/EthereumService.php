<?php

namespace App\Services\Blockchain;

use Illuminate\Support\Facades\Http;
use App\Services\Blockchain\Contracts\BlockchainServiceInterface;
use Illuminate\Support\Facades\Cache;

class EthereumService implements BlockchainServiceInterface
{
		/**
		 * Retorna o saldo de uma carteira Ethereum em ETH
		 */
	public function getBalance(string $address): float
	{
		$cacheKey = 'eth_balance:' . strtolower($address);

		return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($address) {

			$response = Http::post(
				config('blockchain.ethereum.rpc_url'),
				[
					'jsonrpc' => '2.0',
					'method'  => 'eth_getBalance',
					'params'  => [$address, 'latest'],
					'id'      => 1,
				]
			);

			if (!$response->successful()) {
				abort(502, 'Erro ao consultar a blockchain');
			}

			$json = $response->json();

			if (isset($json['error'])) {
				abort(502, 'Erro RPC Ethereum: ' . $json['error']['message']);
			}

			if (!isset($json['result'])) {
				abort(502, 'Resposta inválida da blockchain');
			}

			// HEX (wei) → decimal
			$weiHex = str_replace('0x', '', $json['result']);

			// Usa GMP/BCMath (alta precisão)
			if (function_exists('gmp_init')) {
				$wei = gmp_strval(gmp_init($weiHex, 16), 10);
				return (float) bcdiv($wei, bcpow('10', '18'), 8);
			}

			// fallback
			return hexdec('0x' . $weiHex) / pow(10, 18);
		});
	}
	public function symbol(): string
    {
        return 'ETH';
    }

    public function addressPattern(): string
    {
        return '/^0x[a-fA-F0-9]{40}$/';
    }
}