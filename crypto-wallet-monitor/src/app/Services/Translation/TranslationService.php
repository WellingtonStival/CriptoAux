<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use Throwable;

class TranslationService
{
    /**
     * Traduz uma lista de textos para portugues (PT-BR) via DeepL, numa
     * unica chamada (a API aceita varios parametros "text" no mesmo
     * request - montamos o corpo manualmente em vez de deixar o cliente
     * HTTP serializar o array em "text[0]=...", que a DeepL nao
     * necessariamente reconhece como multiplos textos).
     *
     * Sem chave configurada, ou se a chamada falhar por qualquer motivo,
     * retorna os textos originais sem traduzir - a funcionalidade de
     * noticias continua de pe (em ingles) em vez de quebrar.
     */
    public function translateToPortuguese(array $texts): array
    {
        $texts = array_values($texts);

        if (empty($texts) || !config('translation.deepl.api_key')) {
            return $texts;
        }

        try {
            // A DeepL descontinuou autenticacao via "auth_key" no corpo da
            // requisicao (nov/2025) - agora exige header Authorization.
            $body = collect($texts)
                ->map(fn ($text) => 'text=' . urlencode($text))
                ->implode('&');

            $body .= '&target_lang=PT-BR';

            $response = Http::withHeaders([
                    'Authorization' => 'DeepL-Auth-Key ' . config('translation.deepl.api_key'),
                ])
                ->withBody($body, 'application/x-www-form-urlencoded')
                ->post(config('translation.deepl.base_url') . '/translate');

            if (!$response->successful()) {
                return $texts;
            }

            $translated = collect($response->json('translations'))->pluck('text')->all();

            return count($translated) === count($texts) ? $translated : $texts;
        } catch (Throwable $exception) {
            return $texts;
        }
    }
}
