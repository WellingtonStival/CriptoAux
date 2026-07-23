<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Telegram\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramPoll extends Command
{
    /**
     * Guarda o update_id mais alto ja processado - a API do Telegram
     * repete updates ate voce confirmar (mandando offset = ultimo + 1),
     * entao sem isso reprocessariamos os mesmos /start toda vez que o
     * comando rodasse.
     */
    private const OFFSET_CACHE_KEY = 'telegram_poll_offset';

    protected $signature = 'telegram:poll';

    protected $description = 'Busca mensagens novas do bot do Telegram e conclui o link de conta pendente (/start <codigo>)';

    public function handle(TelegramService $telegram): int
    {
        if (!$telegram->isConfigured()) {
            return self::SUCCESS;
        }

        $offset = Cache::get(self::OFFSET_CACHE_KEY, 0);
        $updates = $telegram->getUpdates($offset);

        foreach ($updates as $update) {
            $this->processUpdate($update, $telegram);
            $offset = $update['update_id'] + 1;
        }

        if ($updates !== []) {
            Cache::forever(self::OFFSET_CACHE_KEY, $offset);
        }

        return self::SUCCESS;
    }

    private function processUpdate(array $update, TelegramService $telegram): void
    {
        $text = $update['text'] ?? '';
        $chatId = $update['chat_id'] ?? null;

        if (!$chatId || !str_starts_with($text, '/start ')) {
            return;
        }

        $code = trim(substr($text, strlen('/start ')));
        $user = User::where('telegram_link_code', $code)->first();

        if (!$user) {
            Log::warning('Codigo de link do Telegram nao encontrado', ['code' => $code]);
            return;
        }

        $user->forceFill([
            'telegram_chat_id' => $chatId,
            'telegram_link_code' => null,
        ])->save();

        $telegram->sendMessage(
            $chatId,
            'Conectado! A partir de agora você recebe seus alertas do Nexfolio por aqui.'
        );
    }
}
