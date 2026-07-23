<?php

return [
    'coingecko' => [
        'base_url' => env('COINGECKO_API_URL', 'https://api.coingecko.com/api/v3'),

        /*
         * Chave gratuita do plano "Demo" (sem cartao, cadastro em
         * coingecko.com). Sem ela, o endpoint de preco de token
         * (/simple/token_price) limita a **1 endereco de contrato por
         * requisicao** - confirmado ao vivo em 2026-07-23, retorna 400
         * "Number of contract addresses in the request exceeds the
         * allowed limit of 1 contract address" a partir do segundo
         * endereco. Com a chave (header x-cg-demo-api-key), o limite
         * documentado sobe pra 515 por requisicao.
         */
        'api_key' => env('COINGECKO_API_KEY'),
    ],

    /*
     * Fear & Greed Index - API publica e gratuita, sem chave, mantida
     * pela Alternative.me. E o indice classico usado por praticamente
     * todo app de cripto (verificado ao vivo em 2026-07-23). Atualiza
     * 1x por dia.
     */
    'fear_greed' => [
        'base_url' => env('FEAR_GREED_API_URL', 'https://api.alternative.me/fng/'),
    ],
];
