<?php

namespace App\Services\Blockchain;

use App\Services\Blockchain\Contracts\BlockchainServiceInterface;

class BlockchainResolver
{
    public function resolve(string $network): BlockchainServiceInterface
    {
        return match ($network) {
            'ethereum' => app(EthereumService::class),
            'solana' => app(SolanaService::class),
            'bitcoin' => app(BitcoinService::class),
            default => throw new \Exception("Blockchain não suportada"),
        };
    }

    /**
     * Redes aceitas hoje pelo sistema. Usado pela validacao de wallets.
     */
    public static function supportedNetworks(): array
    {
        return ['ethereum', 'solana', 'bitcoin'];
    }
}