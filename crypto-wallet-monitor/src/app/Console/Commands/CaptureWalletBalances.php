<?php

namespace App\Console\Commands;

use App\Jobs\RefreshWalletBalance;
use App\Models\Wallet;
use Illuminate\Console\Command;

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
    protected $description = 'Despacha um job de atualizacao de saldo/valor pra cada wallet cadastrada';

    /**
     * Execute the console command.
     *
     * O trabalho pesado (chamar a blockchain, salvar o snapshot) roda no
     * worker da fila, nao aqui - isso permite que wallets sejam
     * processadas em paralelo e com retry automatico por wallet, em vez
     * de um loop sequencial que desiste na primeira falha.
     */
    public function handle(): int
    {
        $walletIds = Wallet::pluck('id');

        foreach ($walletIds as $walletId) {
            RefreshWalletBalance::dispatch($walletId);
        }

        $this->info("{$walletIds->count()} wallet(s) enfileirada(s) para atualizacao.");

        return self::SUCCESS;
    }
}
