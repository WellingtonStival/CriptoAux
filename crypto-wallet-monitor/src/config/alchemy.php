<?php

return [
    /*
     * Descoberta automatica de tokens ERC-20. A RPC comum nao tem como
     * listar "todos os tokens que este endereco possui" - isso exige um
     * indexador. Etherscan tem esse recurso, mas o endpoint especifico
     * (addresstokenbalance) e PRO, nao esta no plano gratuito (confirmado
     * direto na documentacao deles antes de escolher). A Alchemy tem o
     * mesmo recurso (Token API) disponivel no plano gratuito (30M
     * unidades/mes, essa chamada custa 20 unidades).
     *
     * Uma unica chave de API atende varias redes EVM - so precisa
     * habilitar cada rede no dashboard da Alchemy antes de usar (senao a
     * API responde "network not enabled for this app").
     */
    'api_key' => env('ALCHEMY_API_KEY'),

    'base_urls' => [
        'ethereum' => env('ALCHEMY_ETH_BASE_URL', 'https://eth-mainnet.g.alchemy.com/v2'),
        'polygon' => env('ALCHEMY_POLYGON_BASE_URL', 'https://polygon-mainnet.g.alchemy.com/v2'),
        'bnb' => env('ALCHEMY_BNB_BASE_URL', 'https://bnb-mainnet.g.alchemy.com/v2'),
    ],
];
