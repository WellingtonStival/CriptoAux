<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Services\Blockchain\BlockchainResolver;
use App\Services\Blockchain\Contracts\TransactionHistoryProvider;
use Illuminate\Http\Request;

class WalletTransactionController extends Controller
{
    public function index(Request $request, $id, BlockchainResolver $resolver)
    {
        $wallet = Wallet::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $service = $resolver->resolve($wallet->network);

        if (!$service instanceof TransactionHistoryProvider) {
            return response()->json([
                'wallet_id' => $wallet->id,
                'network' => $wallet->network,
                'supported' => false,
                'transactions' => [],
            ]);
        }

        return response()->json([
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
            'symbol' => $service->symbol(),
            'supported' => true,
            'transactions' => $service->getTransactions($wallet->address),
        ]);
    }
}
