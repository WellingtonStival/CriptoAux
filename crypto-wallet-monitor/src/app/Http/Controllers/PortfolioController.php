<?php

namespace App\Http\Controllers;

use App\Models\WalletBalanceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PortfolioController extends Controller
{
    private const PERIODS = [
        '24h' => ['days' => 1, 'bucket' => 'hour'],
        '7d' => ['days' => 7, 'bucket' => 'hour'],
        '30d' => ['days' => 30, 'bucket' => 'day'],
    ];

    /**
     * Agrega o historico de saldo de todas as wallets do usuario num unico
     * valor de patrimonio ao longo do tempo, mais a distribuicao atual por
     * rede. Diferente de WalletHistoryController (uma wallet so), aqui
     * cada ponto do grafico e a soma de todas as wallets.
     *
     * Como wallets nao sao necessariamente capturadas no mesmo instante
     * exato (o job agendado passa por elas em sequencia, e checagens
     * manuais acontecem em qualquer momento), agrupamos os snapshots em
     * "baldes" de tempo (hora ou dia, conforme o periodo) e, dentro de
     * cada balde, usamos o snapshot mais recente de cada wallet - isso
     * evita contar duas vezes uma wallet que foi capturada mais de uma
     * vez no mesmo balde.
     */
    public function history(Request $request)
    {
        $walletIds = $request->user()->wallets()->pluck('id');

        $period = $request->query('period', '7d');
        $config = self::PERIODS[$period] ?? null;
        $bucketUnit = $config['bucket'] ?? 'day';

        $query = WalletBalanceHistory::whereIn('wallet_id', $walletIds)->orderBy('captured_at');

        if ($config !== null) {
            $query->where('captured_at', '>=', now()->subDays($config['days']));
        }

        $points = $query->get()
            ->groupBy(fn ($row) => $row->captured_at->copy()->startOf($bucketUnit)->toIso8601String())
            ->map(fn ($rowsInBucket, $bucketKey) => [
                'captured_at' => $bucketKey,
                'value_usd' => $this->latestPerWallet($rowsInBucket)->sum(fn ($row) => $this->valueOf($row)),
            ])
            ->values();

        // Alocacao atual usa o ultimo snapshot de cada wallet, independente
        // do periodo selecionado no grafico.
        $latestOverall = $this->latestPerWallet(
            WalletBalanceHistory::whereIn('wallet_id', $walletIds)->orderBy('captured_at')->get()
        );

        $currentValueUsd = $latestOverall->sum(fn ($row) => $this->valueOf($row));
        $allocation = $this->allocationByNetwork($latestOverall, $currentValueUsd);

        $first = $points->first();
        $last = $points->last();

        $changeUsd = ($first && $last) ? $last['value_usd'] - $first['value_usd'] : null;
        $changePercent = ($first && $last && $first['value_usd'] > 0)
            ? ($changeUsd / $first['value_usd']) * 100
            : null;

        return response()->json([
            'period' => array_key_exists($period, self::PERIODS) ? $period : 'all',
            'points' => $points,
            'summary' => [
                'current_value_usd' => $currentValueUsd,
                'change_value_usd' => $changeUsd,
                'change_percent' => $changePercent,
                'min_value_usd' => $points->min('value_usd'),
                'max_value_usd' => $points->max('value_usd'),
            ],
            'allocation' => $allocation,
        ]);
    }

    /**
     * Dentro de uma colecao de snapshots, mantem so o mais recente de cada
     * wallet.
     */
    private function latestPerWallet(Collection $rows): Collection
    {
        return $rows->groupBy('wallet_id')
            ->map(fn ($rowsForWallet) => $rowsForWallet->sortByDesc('captured_at')->first());
    }

    private function valueOf(WalletBalanceHistory $row): float
    {
        return $row->price_usd !== null ? $row->balance * $row->price_usd : 0.0;
    }

    private function allocationByNetwork(Collection $latestPerWallet, float $total): Collection
    {
        return $latestPerWallet
            ->groupBy('network')
            ->map(fn ($rows, $network) => $rows->sum(fn ($row) => $this->valueOf($row)))
            ->map(fn ($valueUsd, $network) => [
                'network' => $network,
                'value_usd' => $valueUsd,
                'percent' => $total > 0 ? ($valueUsd / $total) * 100 : 0,
            ])
            ->values();
    }
}
