<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wallet;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
				'address' => [
					'required',
					'string',
					'regex:/^0x[a-fA-F0-9]{40}$/',
					Rule::unique('wallets', 'address'),
				],
				'network' => [
					'required',
					'in:ethereum',
				],
		]);

        $wallet = Wallet::create([
            'address' => $validated['address'],
			'network' => $validated['network'],
			'user_id' => $request->user()->id,
        ]);

        return response()->json($wallet, 201);
    }
	public function index(Request $request)
	{
		return response()->json(
			Wallet::where('user_id', $request->user()->id)
				->orderBy('id', 'desc')
				->paginate(10)
		);
	}
	public function destroy(Request $request, $id)
	{
		$wallet = Wallet::where('id', $id)
			->where('user_id', $request->user()->id)
			->first();

		if (!$wallet) {
			return response()->json([
				'message' => 'Carteira não encontrada',
			], 404);
		}

		$wallet->delete();

		return response()->json([
			'message' => 'Carteira removida com sucesso',
		], 200);
	}
}
