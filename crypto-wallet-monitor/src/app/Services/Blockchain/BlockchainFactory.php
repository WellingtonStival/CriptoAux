<?php

namespace App\Services\Blockchain;

use Exception;
use App\Services\Blockchain\EthereumService;
use App\Services\Blockchain\Contracts\BlockchainServiceInterface;

class BlockchainFactory
{
    public static function make(string $network): BlockchainServiceInterface
    {
        return match ($network) {
            'ethereum' => new EthereumService(),
            default => throw new Exception("Blockchain não suportada: {$network}"),
        };
    }
}
