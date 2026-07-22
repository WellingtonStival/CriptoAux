<?php

namespace App\Jobs;

use App\Models\Wallet;
use App\Services\Blockchain\BlockchainResolver;
use App\Services\Wallet\BalanceHistoryRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshWalletBalance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $walletId)
    {
    }

    /**
     * Backoff crescente entre tentativas - a maior parte das falhas de RPC
     * e transitoria (rate limit, instabilidade momentanea).
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(BlockchainResolver $resolver, BalanceHistoryRecorder $recorder): void
    {
        $wallet = Wallet::find($this->walletId);

        if (!$wallet) {
            return;
        }

        try {
            $service = $resolver->resolve($wallet->network);
            $balance = $service->getBalance($wallet->address);

            $recorder->capture($wallet, $balance);
        } catch (Throwable $exception) {
            Log::warning('Falha ao atualizar saldo em background', [
                'wallet_id' => $wallet->id,
                'network' => $wallet->network,
            ]);

            throw $exception;
        }
    }
}
