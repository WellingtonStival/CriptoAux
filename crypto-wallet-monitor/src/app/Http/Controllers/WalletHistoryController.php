<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\Request;

class WalletHistoryController extends Controller
{
    private const PERIODS = [
        '24h' => 1,
        '7d' => 7,
        '30d' => 30,
    ];

    public function index(Request $request, $id)
    {
        $wallet = Wallet::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $period = $request->query('period', '7d');
        $days = self::PERIODS[$period] ?? null;

        $query = $wallet->balanceHistories()->orderBy('captured_at');

        if ($days !== null) {
            $query->where('captured_at', '>=', now()->subDays($days));
        }

        $points = $query->get()->map(fn ($point) => [
            'captured_at' => $point->captured_at->toIso8601String(),
            'balance' => $point->balance,
            'price_usd' => $point->price_usd,
            'value_usd' => $point->price_usd !== null
                ? $point->balance * $point->price_usd
                : null,
        ]);

        return response()->json([
            'wallet_id' => $wallet->id,
            'network' => $wallet->network,
            'period' => array_key_exists($period, self::PERIODS) ? $period : 'all',
            'points' => $points,
            'summary' => $this->summarize($points),
        ]);
    }

    private function summarize($points): array
    {
        $withValue = $points->filter(fn ($point) => $point['value_usd'] !== null);

        $first = $withValue->first();
        $last = $withValue->last();

        $changeUsd = ($first && $last)
            ? $last['value_usd'] - $first['value_usd']
            : null;

        $changePercent = ($first && $last && $first['value_usd'] > 0)
            ? ($changeUsd / $first['value_usd']) * 100
            : null;

        return [
            'current_balance' => optional($points->last())['balance'],
            'current_value_usd' => optional($points->last())['value_usd'],
            'change_value_usd' => $changeUsd,
            'change_percent' => $changePercent,
            'min_value_usd' => $withValue->min('value_usd'),
            'max_value_usd' => $withValue->max('value_usd'),
        ];
    }
}
