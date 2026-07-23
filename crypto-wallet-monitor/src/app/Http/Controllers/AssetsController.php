<?php

namespace App\Http\Controllers;

use App\Models\WalletToken;
use Illuminate\Http\Request;

class AssetsController extends Controller
{
    /**
     * Visao consolidada: o mesmo token pode existir em varias wallets do
     * usuario (ex: LINK numa wallet e mais LINK noutra) - aqui somamos
     * tudo numa linha so por ativo, em vez de mostrar por wallet.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $tokens = WalletToken::whereHas('wallet', fn ($query) => $query->where('user_id', $userId))
            ->with(['wallet', 'balanceHistories' => fn ($query) => $query->latest('captured_at')->limit(1)])
            ->get();

        $assets = $tokens
            ->groupBy(fn (WalletToken $token) => $token->wallet->network . ':' . strtolower($token->contract_address))
            ->map(function ($group) {
                $first = $group->first();
                $priceUsd = $first->balanceHistories->first()->price_usd ?? null;

                $totalBalance = $group->sum(fn (WalletToken $token) => $token->balanceHistories->first()->balance ?? 0);

                return [
                    'contract_address' => $first->contract_address,
                    'network' => $first->wallet->network,
                    'symbol' => $first->symbol,
                    'name' => $first->name,
                    'logo_url' => $first->logo_url,
                    'balance' => $totalBalance,
                    'price_usd' => $priceUsd,
                    'value_usd' => $priceUsd !== null ? $totalBalance * $priceUsd : null,
                    'wallets_count' => $group->count(),
                ];
            })
            ->sortByDesc(fn ($asset) => $asset['value_usd'] ?? 0)
            ->values();

        return response()->json(['assets' => $assets]);
    }
}
