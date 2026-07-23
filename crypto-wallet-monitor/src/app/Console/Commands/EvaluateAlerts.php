<?php

namespace App\Console\Commands;

use App\Services\Alerts\AlertEvaluationService;
use Illuminate\Console\Command;

class EvaluateAlerts extends Command
{
    protected $signature = 'alerts:evaluate';

    protected $description = 'Avalia as regras de alerta de patrimonio/preco e dispara notificacoes no Telegram (queda de saldo por wallet e avaliada em tempo real, nao aqui)';

    public function handle(AlertEvaluationService $evaluator): int
    {
        $evaluator->evaluatePeriodicRules();

        return self::SUCCESS;
    }
}
