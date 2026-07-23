<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper fino sobre a API do Telegram Bot (gratuita, sem custo por
 * mensagem). Duas operacoes: mandar mensagem pra um chat ja conhecido
 * (sendMessage) e buscar mensagens novas recebidas pelo bot (getUpdates,
 * usado pelo comando de polling que faz o /start funcionar).
 */
class TelegramService
{
    public function isConfigured(): bool
    {
        return (bool) config('telegram.bot_token');
    }

    public function botUsername(): ?string
    {
        return config('telegram.bot_username');
    }

    /**
     * Link de deep-link que abre uma conversa com o bot ja com o comando
     * /start preenchido - clicar nele e o unico passo que o usuario
     * precisa dar pra conectar a conta.
     */
    public function linkUrl(string $code): ?string
    {
        $username = $this->botUsername();

        if (!$username) {
            return null;
        }

        return "https://t.me/{$username}?start={$code}";
    }

    public function sendMessage(string $chatId, string $text): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $response = Http::timeout(5)->retry(2, 200, throw: false)
            ->post($this->apiUrl('sendMessage'), [
                'chat_id' => $chatId,
                'text' => $text,
            ]);

        if (!$response->successful()) {
            Log::warning('Falha ao enviar mensagem no Telegram', [
                'chat_id' => $chatId,
                'status' => $response->status(),
            ]);
        }

        return $response->successful();
    }

    /**
     * Busca mensagens novas recebidas pelo bot desde $offset (o update_id
     * do getUpdates e sempre crescente - mandar offset = ultimo + 1 marca
     * os anteriores como lidos, evitando reprocessar). $timeout=0 pra
     * long-polling nao ser usado aqui - o comando que chama isso ja roda
     * em ciclo curto via scheduler, prefere varias chamadas rapidas a uma
     * conexao aberta por muito tempo.
     *
     * @return array<int, array{update_id: int, chat_id: ?string, text: ?string}>
     */
    public function getUpdates(int $offset): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $response = Http::timeout(10)->retry(2, 200, throw: false)
            ->get($this->apiUrl('getUpdates'), [
                'offset' => $offset,
                'timeout' => 0,
            ]);

        if (!$response->successful()) {
            Log::warning('Falha ao buscar updates do Telegram', ['status' => $response->status()]);
            return [];
        }

        $results = $response->json('result') ?? [];

        return collect($results)
            ->map(fn (array $update) => [
                'update_id' => $update['update_id'],
                'chat_id' => isset($update['message']['chat']['id'])
                    ? (string) $update['message']['chat']['id']
                    : null,
                'text' => $update['message']['text'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function apiUrl(string $method): string
    {
        return rtrim(config('telegram.base_url'), '/') . '/bot' . config('telegram.bot_token') . '/' . $method;
    }
}
