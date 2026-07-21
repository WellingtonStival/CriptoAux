<?php

namespace App\Services\Wallet;

use App\Models\Wallet;
use App\Models\WalletBalanceHistory;
use App\Services\Market\PriceService;

class BalanceHistoryRecorder
{
    public function __construct(
        private PriceService $priceService,
    ) {
    }

    /**
     * Salva um snapshot do saldo (e valor em USD, se o preco estiver
     * disponivel) de uma wallet. O saldo ja deve ter sido consultado pelo
     * chamador (via BlockchainResolver) para evitar uma segunda chamada
     * RPC redundante.
     */
    public function capture(Wallet $wallet, float $balance): WalletBalanceHistory
    {
        $prices = $this->priceService->current();
        $priceUsd = $prices[$wallet->network]['usd'] ?? null;

        return WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
            'balance' => $balance,
            'price_usd' => $priceUsd,
            'captured_at' => now(),
        ]);
    }
}
