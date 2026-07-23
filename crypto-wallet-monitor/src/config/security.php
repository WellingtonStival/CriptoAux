<?php

return [
    /*
     * GoPlus Security - API publica e gratuita, sem chave (verificado ao
     * vivo em 2026-07-23, HTTP 200 sem nenhum header de autenticacao),
     * usada pra listar aprovacoes de token (ERC-20 approve/allowance)
     * concedidas por uma wallet, com sinalizacao de risco por contrato
     * (open source, malicioso, etc).
     */
    'goplus' => [
        'base_url' => env('GOPLUS_API_URL', 'https://api.gopluslabs.io/api/v2'),
    ],
];
