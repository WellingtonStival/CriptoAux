<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wallet;
use Illuminate\Validation\Rule;
use App\Services\Blockchain\BlockchainResolver;

class WalletController extends Controller
{
    public function store(Request $request, BlockchainResolver $resolver)
    {
        $validated = $request->validate([
				'network' => [
					'required',
					Rule::in(BlockchainResolver::supportedNetworks()),
				],
				'address' => [
					'required',
					'string',
					function ($attribute, $value, $fail) use ($request, $resolver) {
						$network = $request->input('network');

						if (!in_array($network, BlockchainResolver::supportedNetworks(), true)) {
							return;
						}

						$service = $resolver->resolve($network);

						if (!preg_match($service->addressPattern(), $value)) {
							$fail('Endereço inválido para a blockchain selecionada.');
						}
					},
					Rule::unique('wallets', 'address'),
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
