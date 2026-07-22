<?php

namespace App\Services\Blockchain;

use Illuminate\Support\Facades\Http;
use App\Services\Blockchain\Contracts\BlockchainServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EthereumService implements BlockchainServiceInterface
{
	private function cacheKey(string $address): string
	{
		return 'eth_balance:' . strtolower($address);
	}

	public function getCachedBalance(string $address): ?float
	{
		return Cache::get($this->cacheKey($address));
	}

		/**
		 * Retorna o saldo de uma carteira Ethereum em ETH. Com
		 * $forceRefresh, ignora um cache ainda valido e busca ao vivo
		 * mesmo assim (usado pelo botao manual de atualizar saldo).
		 */
	public function getBalance(string $address, bool $forceRefresh = false): float
	{
		$cacheKey = $this->cacheKey($address);

		if ($forceRefresh) {
			Cache::forget($cacheKey);
		}

		return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($address) {

			$response = Http::timeout(5)->retry(2, 200, throw: false)->post(
				config('blockchain.ethereum.rpc_url'),
				[
					'jsonrpc' => '2.0',
					'method'  => 'eth_getBalance',
					'params'  => [$address, 'latest'],
					'id'      => 1,
				]
			);

			if (!$response->successful()) {
				Log::warning('Falha ao consultar saldo Ethereum', [
					'address' => $address,
					'status' => $response->status(),
				]);
				abort(502, 'Erro ao consultar a blockchain');
			}

			$json = $response->json();

			if (isset($json['error'])) {
				Log::warning('Erro RPC Ethereum', ['address' => $address, 'error' => $json['error']]);
				abort(502, 'Erro RPC Ethereum: ' . $json['error']['message']);
			}

			if (!isset($json['result'])) {
				Log::warning('Resposta invalida da RPC Ethereum', ['address' => $address]);
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