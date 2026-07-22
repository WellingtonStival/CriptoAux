<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\RefreshWalletBalance;
use App\Models\Wallet;
use App\Services\Blockchain\BlockchainResolver;
use App\Services\Blockchain\Contracts\BlockchainServiceInterface;
use App\Services\Wallet\BalanceHistoryRecorder;
use Illuminate\Support\Facades\Log;
use Throwable;

class WalletBalanceController extends Controller
{
	/**
	 * Consulta o saldo de uma wallet.
	 *
	 * Por padrao, essa rota nunca trava esperando a blockchain:
	 * - cache quente -> responde na hora;
	 * - cache frio, mas ja existe historico -> despacha atualizacao em
	 *   background (fila) e responde na hora com o ultimo saldo salvo
	 *   (`stale: true`);
	 * - wallet novissima sem nenhum historico -> nao ha o que servir,
	 *   entao busca ao vivo essa unica vez.
	 *
	 * `?force=true` (usado pelo botao "Atualizar saldo") pula tudo isso e
	 * sempre busca ao vivo, porque foi um pedido explicito do usuario.
	 */
    public function show(Request $request, $id, BlockchainResolver $resolver, BalanceHistoryRecorder $recorder)
	{
		$wallet = Wallet::where('id', $id)
			->where('user_id', $request->user()->id)
			->firstOrFail();

		$service = $resolver->resolve($wallet->network);
		$forceRefresh = $request->boolean('force');

		if (!$forceRefresh) {
			$cached = $service->getCachedBalance($wallet->address);

			if ($cached !== null) {
				return $this->respond($wallet, $service, $cached, stale: false);
			}

			$lastKnown = $wallet->balanceHistories()->latest('captured_at')->first();

			if ($lastKnown) {
				RefreshWalletBalance::dispatch($wallet->id);

				return $this->respond(
					$wallet,
					$service,
					$lastKnown->balance,
					stale: true,
					capturedAt: $lastKnown->captured_at,
				);
			}

			// wallet sem nenhum historico ainda - nao ha fallback possivel,
			// cai pra busca ao vivo abaixo.
		}

		try {
			$balance = $service->getBalance($wallet->address, forceRefresh: $forceRefresh);
		} catch (Throwable $exception) {
			$lastKnown = $wallet->balanceHistories()->latest('captured_at')->first();

			if (!$lastKnown) {
				throw $exception;
			}

			Log::warning('Servindo saldo desatualizado apos falha na blockchain', [
				'wallet_id' => $wallet->id,
				'network' => $wallet->network,
			]);

			return $this->respond(
				$wallet,
				$service,
				$lastKnown->balance,
				stale: true,
				capturedAt: $lastKnown->captured_at,
			);
		}

		$recorder->capture($wallet, $balance);

		return $this->respond($wallet, $service, $balance, stale: false);
	}

	private function respond(
		Wallet $wallet,
		BlockchainServiceInterface $service,
		float $balance,
		bool $stale,
		?string $capturedAt = null,
	) {
		return response()->json(array_filter([
			'wallet_id' => $wallet->id,
			'address'   => $wallet->address,
			'network'   => $wallet->network,
			'symbol'    => $service->symbol(),
			'balance'   => $balance,
			'stale'     => $stale,
			'captured_at' => $capturedAt,
		], fn ($value) => $value !== null));
	}
}
