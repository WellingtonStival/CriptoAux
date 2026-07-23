<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TelegramController extends Controller
{
    public function status(Request $request, TelegramService $telegram)
    {
        return response()->json([
            'linked' => $request->user()->hasTelegramLinked(),
            'configured' => $telegram->isConfigured(),
        ]);
    }

    /**
     * Gera um codigo novo e o link de deep-link do bot. O usuario clica
     * no link, o Telegram abre a conversa com /start <codigo> ja digitado,
     * e o comando `telegram:poll` (rodando no scheduler) completa o link
     * quando essa mensagem chegar.
     */
    public function generateLinkCode(Request $request, TelegramService $telegram)
    {
        if (!$telegram->isConfigured()) {
            return response()->json([
                'message' => 'Integração com Telegram não configurada no servidor.',
            ], 422);
        }

        $user = $request->user();
        $code = Str::random(24);

        $user->forceFill(['telegram_link_code' => $code])->save();

        return response()->json([
            'code' => $code,
            'link_url' => $telegram->linkUrl($code),
        ]);
    }

    public function unlink(Request $request)
    {
        $request->user()->forceFill([
            'telegram_chat_id' => null,
            'telegram_link_code' => null,
        ])->save();

        return response()->json(['message' => 'Telegram desconectado.']);
    }
}
