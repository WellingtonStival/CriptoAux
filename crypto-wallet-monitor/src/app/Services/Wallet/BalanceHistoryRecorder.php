<?php

namespace App\Services\Wallet;

use App\Models\Wallet;
use App\Models\WalletBalanceHistory;
use App\Services\Alerts\AlertEvaluationService;
use App\Services\Market\PriceService;

class BalanceHistoryRecorder
{
    /**
     * Intervalo minimo entre snapshots da mesma wallet. Evita que o
     * auto-refresh da tela (a cada ~60s) gere um ponto de historico novo
     * a cada consulta - a captura agendada de hora em hora ja garante
     * historico continuo mesmo sem ninguem com a tela aberta.
     */
    private const MIN_INTERVAL_MINUTES = 5;

    public function __construct(
        private PriceService $priceService,
        private AlertEvaluationService $alertEvaluator,
    ) {
    }

    /**
     * Salva um snapshot do saldo (e valor em USD, se o preco estiver
     * disponivel) de uma wallet. O saldo ja deve ter sido consultado pelo
     * chamador (via BlockchainResolver) para evitar uma segunda chamada
     * RPC redundante. Retorna null quando o snapshot foi descartado por
     * estar dentro do intervalo minimo do ultimo registrado.
     */
    public function capture(Wallet $wallet, float $balance): ?WalletBalanceHistory
    {
        $lastCapture = $wallet->balanceHistories()->latest('captured_at')->first();

        if ($lastCapture && $lastCapture->captured_at->gt(now()->subMinutes(self::MIN_INTERVAL_MINUTES))) {
            return null;
        }

        $prices = $this->priceService->current();
        $priceUsd = $prices[$wallet->network]['usd'] ?? null;

        $snapshot = WalletBalanceHistory::create([
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
            'balance' => $balance,
            'price_usd' => $priceUsd,
            'captured_at' => now(),
        ]);

        if ($lastCapture) {
            $this->alertEvaluator->checkWalletBalanceDrop($wallet, $lastCapture->balance, $balance);
        }

        return $snapshot;
    }
}
