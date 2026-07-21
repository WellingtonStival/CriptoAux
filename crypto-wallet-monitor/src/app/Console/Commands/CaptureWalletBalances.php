<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use App\Services\Blockchain\BlockchainResolver;
use App\Services\Wallet\BalanceHistoryRecorder;
use Illuminate\Console\Command;
use Throwable;

class CaptureWalletBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallets:capture-balances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Salva um snapshot do saldo/valor atual de todas as wallets cadastradas';

    /**
     * Execute the console command.
     */
    public function handle(BlockchainResolver $resolver, BalanceHistoryRecorder $recorder): int
    {
        $wallets = Wallet::all();
        $captured = 0;

        foreach ($wallets as $wallet) {
            try {
                $service = $resolver->resolve($wallet->network);
                $balance = $service->getBalance($wallet->address);

                $recorder->capture($wallet, $balance);
                $captured++;
            } catch (Throwable $exception) {
                $this->error("Falha ao capturar saldo da wallet {$wallet->id}: {$exception->getMessage()}");
            }
        }

        $this->info("{$captured}/{$wallets->count()} wallet(s) capturadas.");

        return self::SUCCESS;
    }
}
