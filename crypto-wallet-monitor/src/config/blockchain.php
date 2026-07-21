<?php

return [
    'ethereum' => [
        'rpc_url' => env('ETH_PUBLIC_RPC_URL', env('ETH_RPC_URL')),
        'symbol' => 'ETH',
        'decimals' => 18,
    ],
    'solana' => [
        'rpc_url' => env('SOL_RPC_URL', 'https://api.mainnet-beta.solana.com'),
        'symbol' => 'SOL',
        'decimals' => 9,
    ],
    'bitcoin' => [
        'api_url' => env('BTC_EXPLORER_URL', 'https://blockstream.info/api'),
        'symbol' => 'BTC',
        'decimals' => 8,
    ],
];
