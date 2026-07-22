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
     * rede e a concentracao (por rede e por wallet). Diferente de
     * WalletHistoryController (uma wallet so), aqui cada ponto do grafico e
     * a soma de todas as wallets.
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
        $wallets = $request->user()->wallets()->get(['id', 'address', 'name']);
        $walletIds = $wallets->pluck('id');

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

        // Alocacao/concentracao atuais usam o ultimo snapshot de cada
        // wallet, independente do periodo selecionado no grafico.
        $latestOverall = $this->latestPerWallet(
            WalletBalanceHistory::whereIn('wallet_id', $walletIds)->orderBy('captured_at')->get()
        );

        $currentValueUsd = $latestOverall->sum(fn ($row) => $this->valueOf($row));

        $networkTotals = $latestOverall->groupBy('network')
            ->map(fn ($rows) => $rows->sum(fn ($row) => $this->valueOf($row)));

        $walletTotals = $latestOverall->mapWithKeys(
            fn ($row) => [$row->wallet_id => $this->valueOf($row)]
        );

        $allocation = $networkTotals
            ->map(fn ($valueUsd, $network) => [
                'network' => $network,
                'value_usd' => $valueUsd,
                'percent' => $currentValueUsd > 0 ? ($valueUsd / $currentValueUsd) * 100 : 0,
            ])
            ->values();

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
            'concentration' => [
                'by_network' => $this->networkConcentration($networkTotals, $currentValueUsd),
                'by_wallet' => $this->walletConcentration($walletTotals, $currentValueUsd, $wallets),
            ],
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

    /**
     * Indice de Herfindahl-Hirschman (HHI): soma dos quadrados dos
     * percentuais de cada posicao. Quanto maior, mais concentrado.
     * Padrao de mercado para medir concentracao/diversificacao (usado ate
     * em analise antitruste). Faixa 0-10000: <1500 diversificado,
     * 1500-2500 moderado, >2500 concentrado.
     *
     * Chamado "concentracao", nao "risco": e um fato objetivo sobre a
     * distribuicao do patrimonio, nao uma opiniao/recomendacao de
     * investimento.
     */
    private function concentrationSummary(Collection $valuesByKey, float $total): array
    {
        if ($total <= 0 || $valuesByKey->isEmpty()) {
            return [
                'hhi' => 0.0,
                'level' => 'indefinido',
                'top_percent' => 0.0,
                'top_key' => null,
            ];
        }

        $percentages = $valuesByKey->map(fn ($value) => ($value / $total) * 100);
        $hhi = round($percentages->sum(fn ($percent) => $percent ** 2), 1);
        $topKey = $percentages->sortDesc()->keys()->first();

        return [
            'hhi' => $hhi,
            'level' => match (true) {
                $hhi < 1500 => 'diversificado',
                $hhi < 2500 => 'moderado',
                default => 'concentrado',
            },
            'top_percent' => round($percentages[$topKey], 1),
            'top_key' => $topKey,
        ];
    }

    private function networkConcentration(Collection $networkTotals, float $total): array
    {
        $summary = $this->concentrationSummary($networkTotals, $total);

        $summary['top_network'] = $summary['top_key'];
        unset($summary['top_key']);

        return $summary;
    }

    private function walletConcentration(Collection $walletTotals, float $total, Collection $wallets): array
    {
        $summary = $this->concentrationSummary($walletTotals, $total);

        $topWallet = $summary['top_key'] !== null
            ? $wallets->firstWhere('id', $summary['top_key'])
            : null;

        $summary['top_wallet_label'] = $topWallet
            ? ($topWallet->name ?: $this->shortAddress($topWallet->address))
            : null;

        unset($summary['top_key']);

        return $summary;
    }

    private function shortAddress(string $address): string
    {
        return strlen($address) > 12
            ? substr($address, 0, 6) . '...' . substr($address, -4)
            : $address;
    }
}
