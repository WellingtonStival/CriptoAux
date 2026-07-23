<?php

return [
    'ethereum' => [
        'rpc_url' => env('ETH_PUBLIC_RPC_URL', env('ETH_RPC_URL')),
        'symbol' => 'ETH',
        'decimals' => 18,
    ],
    'polygon' => [
        'rpc_url' => env('POLYGON_RPC_URL', 'https://polygon-bor-rpc.publicnode.com'),
        'symbol' => 'POL',
        'decimals' => 18,
    ],
    'bnb' => [
        'rpc_url' => env('BNB_RPC_URL', 'https://bsc-dataseed.binance.org'),
        'symbol' => 'BNB',
        'decimals' => 18,
    ],
    'solana' => [
        'rpc_url' => env('SOL_RPC_URL', 'https://api.mainnet-beta.solana.com'),
        'symbol' => 'SOL',
        'decimals' => 9,
        // API publica da Jupiter (lite-api, sem chave) usada so pra resolver
        // nome/simbolo dos tokens SPL descobertos - a RPC da Solana nao
        // devolve isso.
        'token_metadata_url' => env('SOL_JUPITER_TOKENS_URL', 'https://lite-api.jup.ag/tokens/v2/search'),
    ],
    'bitcoin' => [
        'api_url' => env('BTC_EXPLORER_URL', 'https://blockstream.info/api'),
        'symbol' => 'BTC',
        'decimals' => 8,
    ],
];
