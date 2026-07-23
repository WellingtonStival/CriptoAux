<?php

namespace App\Services\Market;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fear & Greed Index (Alternative.me) - indice classico de sentimento do
 * mercado cripto, 0 (medo extremo) a 100 (ganancia extrema). API publica,
 * sem chave, atualiza 1x por dia - por isso o cache longo (1h e suficiente
 * pra nao bater na API a cada request, sem risco de mostrar dado velho
 * de verdade).
 */
class FearGreedService
{
    private const CLASSIFICATIONS_PT = [
        'Extreme Fear' => 'Medo Extremo',
        'Fear' => 'Medo',
        'Neutral' => 'Neutro',
        'Greed' => 'Ganância',
        'Extreme Greed' => 'Ganância Extrema',
    ];

    /**
     * @return array{date: string, value: int, classification: string}|null
     */
    public function current(): ?array
    {
        $points = $this->fetch(1);

        return $points[0] ?? null;
    }

    /**
     * @return array<int, array{date: string, value: int, classification: string}>
     */
    public function history(int $days): array
    {
        // A API so aceita limit (quantidade de pontos), nao um intervalo de
        // datas - como e 1 ponto por dia, days vira o limit direto. 0 = tudo.
        return array_reverse($this->fetch($days === 0 ? 0 : $days));
    }

    private function fetch(int $limit): array
    {
        $cacheKey = 'fear_greed:' . $limit;

        return Cache::remember($cacheKey, now()->addHour(), function () use ($limit) {
            $response = Http::timeout(5)->retry(2, 200, throw: false)
                ->get(config('market.fear_greed.base_url'), [
                    'limit' => $limit,
                    'format' => 'json',
                ]);

            if (!$response->successful()) {
                Log::warning('Falha ao consultar Fear & Greed Index', ['status' => $response->status()]);
                return [];
            }

            $entries = $response->json('data') ?? [];

            return collect($entries)
                ->map(fn (array $entry) => [
                    'date' => Carbon::createFromTimestamp((int) $entry['timestamp'])->toDateString(),
                    'value' => (int) $entry['value'],
                    'classification' => self::CLASSIFICATIONS_PT[$entry['value_classification']] ?? $entry['value_classification'],
                ])
                ->values()
                ->all();
        });
    }
}
