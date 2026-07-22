<?php

namespace App\Services\News;

use App\Services\Translation\TranslationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

class NewsService
{
    /**
     * Cache de 10 minutos (bem mais longo que preco/saldo, que sao 60s):
     * noticia nao muda a cada minuto, e isso poupa as fontes RSS de
     * chamada repetida a toa.
     */
    private const CACHE_SECONDS = 600;

    /**
     * Quantas noticias manter no pool combinado (as 3 fontes juntas),
     * antes de qualquer filtro por moeda. Precisa ser bem maior que o
     * limite por aba (self::PER_NETWORK_LIMIT) - do contrario o corte
     * geral come o espaco antes do filtro por moeda rodar, deixando as
     * abas de moeda especifica com poucos itens mesmo quando os feeds
     * originais tem bastante coisa sobre aquele assunto.
     */
    private const MAX_POOL_SIZE = 120;

    /**
     * Quantas noticias mostrar quando filtrado por uma moeda especifica
     * ou por "other" (sem moeda identificada). A aba "Todas" nao usa
     * esse limite - mostra o pool inteiro.
     */
    private const PER_NETWORK_LIMIT = 15;

    /**
     * Valor especial de rede para noticias gerais de cripto/mercado que
     * a heuristica de palavra-chave nao conseguiu associar a nenhuma
     * moeda especifica.
     */
    public const OTHER = 'other';

    /**
     * Feeds RSS publicos de fontes de noticias cripto conhecidas. Sem
     * chave de API, sem conta, sem custo - diferente de APIs de
     * agregacao (ex: CryptoPanic), RSS e um padrao web estavel que nao
     * costuma ser descontinuado da noite pro dia.
     */
    private const FEEDS = [
        'https://www.coindesk.com/arc/outboundfeeds/rss/',
        'https://cointelegraph.com/rss',
        'https://decrypt.co/feed',
    ];

    /**
     * Palavras-chave usadas para "adivinhar" a que rede uma noticia se
     * refere, ja que RSS nao tem marcacao nativa por moeda (diferente de
     * uma API de agregacao dedicada). E uma heuristica simples (contem a
     * palavra no titulo/resumo?), nao 100% precisa - uma noticia pode
     * ficar sem marcacao nenhuma, ou marcada com mais de uma rede.
     */
    private const KEYWORDS = [
        'bitcoin' => ['bitcoin', 'btc'],
        'ethereum' => ['ethereum', 'eth'],
        'solana' => ['solana', 'sol'],
    ];

    public function __construct(
        private TranslationService $translationService,
    ) {
    }

    /**
     * Retorna as noticias mais recentes das moedas suportadas (ou de uma
     * unica moeda, se $network for informado).
     *
     * Cache GLOBAL (nao por usuario) - noticia e o mesmo conteudo pra
     * todo mundo, entao uma unica rodada de busca nos feeds atende toda a
     * base de usuarios, nao uma por usuario.
     */
    public function latest(?string $network = null): array
    {
        $all = Cache::remember('crypto_news:all', now()->addSeconds(self::CACHE_SECONDS), function () {
            return $this->fetchAndTagAll();
        });

        if ($network === null) {
            return $all;
        }

        $filtered = $network === self::OTHER
            ? collect($all)->filter(fn ($item) => empty($item['currencies']))
            : collect($all)->filter(fn ($item) => in_array($network, $item['currencies'], true));

        return $filtered->take(self::PER_NETWORK_LIMIT)->values()->all();
    }

    private function fetchAndTagAll(): array
    {
        $items = collect(self::FEEDS)
            ->flatMap(fn ($feedUrl) => $this->fetchFeed($feedUrl))
            ->map(fn ($item) => [
                ...$item,
                'currencies' => $this->detectCurrencies($item['title'] . ' ' . $item['summary']),
            ])
            ->sortByDesc('published_at')
            ->take(self::MAX_POOL_SIZE)
            ->values();

        return $this->translate($items)->all();
    }

    /**
     * Traduz titulo e resumo de todas as noticias numa unica chamada
     * (nao uma por noticia), executada so uma vez por ciclo de cache
     * (10 min) - o mesmo principio de escala usado no resto do servico.
     * Sem chave de tradutor configurada, TranslationService devolve os
     * textos originais (em ingles) sem quebrar nada.
     */
    private function translate(Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return $items;
        }

        $titles = $items->pluck('title')->all();
        $summaries = $items->pluck('summary')->all();

        $translated = $this->translationService->translateToPortuguese(
            array_merge($titles, $summaries)
        );

        $translatedTitles = array_slice($translated, 0, count($titles));
        $translatedSummaries = array_slice($translated, count($titles));

        return $items->values()->map(fn ($item, $index) => [
            ...$item,
            'title' => $translatedTitles[$index] ?? $item['title'],
            'summary' => $translatedSummaries[$index] ?? $item['summary'],
        ]);
    }

    /**
     * Busca e converte um feed RSS em itens normalizados. Uma fonte fora
     * do ar ou com XML invalido nao derruba as outras - so retorna vazio
     * para aquela fonte.
     */
    private function fetchFeed(string $feedUrl): Collection
    {
        try {
            $response = Http::timeout(5)->get($feedUrl);

            if (!$response->successful()) {
                return collect();
            }

            $xml = new SimpleXMLElement($response->body());
            $sourceName = (string) ($xml->channel->title ?? parse_url($feedUrl, PHP_URL_HOST));

            $items = collect();

            foreach ($xml->channel->item as $entry) {
                $items->push([
                    'title' => trim((string) $entry->title),
                    'url' => trim((string) $entry->link),
                    'source' => $sourceName,
                    'summary' => strip_tags((string) ($entry->description ?? '')),
                    'published_at' => isset($entry->pubDate)
                        ? Carbon::parse((string) $entry->pubDate)->toIso8601String()
                        : null,
                ]);
            }

            return $items;
        } catch (Throwable $exception) {
            return collect();
        }
    }

    private function detectCurrencies(string $text): array
    {
        $lower = strtolower($text);
        $matches = [];

        foreach (self::KEYWORDS as $network => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $matches[] = $network;
                    break;
                }
            }
        }

        return $matches;
    }
}
