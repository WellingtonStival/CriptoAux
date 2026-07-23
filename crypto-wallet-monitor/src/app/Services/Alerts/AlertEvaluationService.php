<?php

namespace App\Services\Alerts;

use App\Models\AlertRule;
use App\Models\Wallet;
use App\Models\WalletBalanceHistory;
use App\Services\Market\PriceService;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\Collection;

/**
 * Avalia as regras de alerta do usuario e dispara uma mensagem no
 * Telegram quando uma condicao e atingida. Tres tipos, dois caminhos de
 * disparo diferentes:
 *
 * - wallet_balance_drop: avaliado no exato momento em que um novo
 *   snapshot de saldo e salvo (chamado por BalanceHistoryRecorder),
 *   comparando com o snapshot anterior da mesma wallet.
 * - portfolio_change / price_change: nao tem um "momento" natural (dependem
 *   de varias wallets ou nao dependem de wallet nenhuma) - avaliados
 *   periodicamente pelo comando agendado `alerts:evaluate`.
 */
class AlertEvaluationService
{
    /**
     * Nao dispara o mesmo alerta de novo antes desse intervalo, mesmo que
     * a condicao continue verdadeira em avaliacoes seguintes - sem isso,
     * uma queda de patrimonio persistente reenviaria mensagem a cada
     * ciclo de avaliacao.
     */
    private const DEBOUNCE_HOURS = 6;

    public function __construct(
        private readonly TelegramService $telegram,
        private readonly PriceService $priceService,
    ) {
    }

    public function checkWalletBalanceDrop(Wallet $wallet, float $previousBalance, float $currentBalance): void
    {
        if ($previousBalance <= 0 || $currentBalance >= $previousBalance) {
            return;
        }

        $dropPercent = (($previousBalance - $currentBalance) / $previousBalance) * 100;

        $rules = AlertRule::query()
            ->where('user_id', $wallet->user_id)
            ->where('type', AlertRule::TYPE_WALLET_BALANCE_DROP)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('wallet_id')->orWhere('wallet_id', $wallet->id))
            ->get();

        foreach ($rules as $rule) {
            if ($dropPercent < $rule->threshold_percent || !$this->readyToFire($rule)) {
                continue;
            }

            $label = $wallet->name ?: $this->shortAddress($wallet->address);

            $this->fire($rule, sprintf(
                "⚠️ Alerta Nexfolio\nO saldo da wallet \"%s\" caiu %.1f%% (de %s pra %s).",
                $label,
                $dropPercent,
                rtrim(rtrim(number_format($previousBalance, 8), '0'), '.'),
                rtrim(rtrim(number_format($currentBalance, 8), '0'), '.')
            ));
        }
    }

    /**
     * Chamado pelo comando agendado `alerts:evaluate` - varre todas as
     * regras ativas de portfolio_change e price_change de todos os
     * usuarios com Telegram conectado.
     */
    public function evaluatePeriodicRules(): void
    {
        $rules = AlertRule::query()
            ->whereIn('type', [AlertRule::TYPE_PORTFOLIO_CHANGE, AlertRule::TYPE_PRICE_CHANGE])
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->whereNotNull('telegram_chat_id'))
            ->with('user')
            ->get()
            ->groupBy('type');

        foreach ($rules->get(AlertRule::TYPE_PORTFOLIO_CHANGE, collect()) as $rule) {
            $this->evaluatePortfolioChange($rule);
        }

        foreach ($rules->get(AlertRule::TYPE_PRICE_CHANGE, collect()) as $rule) {
            $this->evaluatePriceChange($rule);
        }
    }

    private function evaluatePortfolioChange(AlertRule $rule): void
    {
        $walletIds = $rule->user->wallets()->pluck('id');

        if ($walletIds->isEmpty()) {
            return;
        }

        $current = $this->portfolioValueAt($walletIds, now());
        $previous = $this->portfolioValueAt($walletIds, now()->subDay());

        if ($current === null || $previous === null || $previous <= 0) {
            return;
        }

        $changePercent = (($current - $previous) / $previous) * 100;

        if (!$this->matchesDirection($rule->direction, $changePercent, $rule->threshold_percent)) {
            return;
        }

        if (!$this->readyToFire($rule)) {
            return;
        }

        $arrow = $changePercent >= 0 ? '📈' : '📉';

        $this->fire($rule, sprintf(
            "%s Alerta Nexfolio\nSeu patrimônio total %s %.1f%% nas últimas 24h (de %s pra %s).",
            $arrow,
            $changePercent >= 0 ? 'subiu' : 'caiu',
            abs($changePercent),
            '$' . number_format($previous, 2),
            '$' . number_format($current, 2)
        ));
    }

    private function evaluatePriceChange(AlertRule $rule): void
    {
        if (!$rule->network) {
            return;
        }

        $prices = $this->priceService->current();
        $changePercent = $prices[$rule->network]['change_24h'] ?? null;

        if ($changePercent === null) {
            return;
        }

        if (!$this->matchesDirection($rule->direction, $changePercent, $rule->threshold_percent)) {
            return;
        }

        if (!$this->readyToFire($rule)) {
            return;
        }

        $arrow = $changePercent >= 0 ? '📈' : '📉';

        $this->fire($rule, sprintf(
            "%s Alerta Nexfolio\nO preço de %s %s %.1f%% nas últimas 24h.",
            $arrow,
            ucfirst($rule->network),
            $changePercent >= 0 ? 'subiu' : 'caiu',
            abs($changePercent)
        ));
    }

    private function portfolioValueAt(Collection $walletIds, \DateTimeInterface $when): ?float
    {
        $rows = WalletBalanceHistory::whereIn('wallet_id', $walletIds)
            ->where('captured_at', '<=', $when)
            ->orderByDesc('captured_at')
            ->get()
            ->groupBy('wallet_id')
            ->map(fn ($rowsForWallet) => $rowsForWallet->first());

        if ($rows->isEmpty()) {
            return null;
        }

        return (float) $rows->sum(fn ($row) => $row->price_usd !== null ? $row->balance * $row->price_usd : 0.0);
    }

    private function matchesDirection(string $direction, float $changePercent, float $threshold): bool
    {
        return match ($direction) {
            'down' => $changePercent <= 0 && abs($changePercent) >= $threshold,
            'up' => $changePercent >= 0 && $changePercent >= $threshold,
            default => abs($changePercent) >= $threshold,
        };
    }

    private function readyToFire(AlertRule $rule): bool
    {
        return $rule->last_triggered_at === null
            || $rule->last_triggered_at->lt(now()->subHours(self::DEBOUNCE_HOURS));
    }

    private function fire(AlertRule $rule, string $message): void
    {
        $chatId = $rule->user?->telegram_chat_id;

        if (!$chatId) {
            return;
        }

        $sent = $this->telegram->sendMessage($chatId, $message);

        if ($sent) {
            $rule->forceFill(['last_triggered_at' => now()])->save();
        }
    }

    private function shortAddress(string $address): string
    {
        return strlen($address) > 12
            ? substr($address, 0, 6) . '...' . substr($address, -4)
            : $address;
    }
}
