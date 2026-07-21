<?php

return [
    'ethereum' => [
        'rpc_url' => env('ETH_PUBLIC_RPC_URL', env('ETH_RPC_URL')),
        'symbol' => 'ETH',
        'decimals' => 18,
    ],
];
