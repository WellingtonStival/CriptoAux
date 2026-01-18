<?php

namespace App\Services\Blockchain;

use App\Services\Blockchain\Contracts\BlockchainServiceInterface;

class BlockchainResolver
{
    public function resolve(string $network): BlockchainServiceInterface
    {
        return match ($network) {
            'ethereum' => app(EthereumService::class),
            default => throw new \Exception("Blockchain não suportada"),
        };
    }
}