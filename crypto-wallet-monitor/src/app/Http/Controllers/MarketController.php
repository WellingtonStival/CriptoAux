<?php

namespace App\Http\Controllers;

use App\Services\Market\AltcoinSeasonService;
use App\Services\Market\FearGreedService;
use App\Services\Market\GlobalMarketService;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    private const HISTORY_PERIODS = [
        '30d' => 30,
        '1y' => 365,
        'all' => 0,
    ];

    /**
     * Snapshot atual dos indicadores de mercado (nao depende de wallet
     * nenhuma do usuario) - pensado pra alimentar os "cards" da tela de
     * Mercado. Cada indicador falha de forma independente: se um vendor
     * cair, os outros continuam aparecendo.
     */
    public function overview(
        FearGreedService $fearGreed,
        GlobalMarketService $globalMarket,
        AltcoinSeasonService $altcoinSeason
    ) {
        return response()->json([
            'fear_greed' => $fearGreed->current(),
            'global' => $globalMarket->current(),
            'altcoin_season' => $altcoinSeason->current(),
        ]);
    }

    public function fearGreedHistory(Request $request, FearGreedService $fearGreed)
    {
        $period = $request->query('period', '30d');
        $days = self::HISTORY_PERIODS[$period] ?? self::HISTORY_PERIODS['30d'];

        return response()->json([
            'period' => array_key_exists($period, self::HISTORY_PERIODS) ? $period : '30d',
            'points' => $fearGreed->history($days),
        ]);
    }
}
