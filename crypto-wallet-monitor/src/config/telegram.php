<?php

return [
    /*
     * Token do bot, gerado pelo @BotFather no Telegram (gratuito, sem
     * cartao). Sem essa chave, envio de alerta e o comando de polling
     * simplesmente nao fazem nada (degrada sem quebrar).
     */
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    /*
     * Username do bot (sem @), usado pra montar o link de conexao
     * https://t.me/{username}?start={codigo}.
     */
    'bot_username' => env('TELEGRAM_BOT_USERNAME'),

    'base_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),
];
