<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Services\Blockchain\BlockchainResolver;
use App\Services\Wallet\BalanceHistoryRecorder;

class WalletBalanceController extends Controller
{
    public function show(Request $request, $id, BlockchainResolver $resolver, BalanceHistoryRecorder $recorder)
	{
		$wallet = Wallet::where('id', $id)
			->where('user_id', $request->user()->id)
			->firstOrFail();

		$service = $resolver->resolve($wallet->network);

		$balance = $service->getBalance($wallet->address);

		$recorder->capture($wallet, $balance);

		return response()->json([
			'wallet_id' => $wallet->id,
			'address'   => $wallet->address,
			'network'   => $wallet->network,
			'symbol'    => $service->symbol(),
			'balance'   => $balance,
		]);
	}
}