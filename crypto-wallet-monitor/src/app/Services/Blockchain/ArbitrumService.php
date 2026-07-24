<?php

namespace App\Services\Blockchain;

class ArbitrumService extends AbstractEvmChainService
{
    protected function network(): string
    {
        return 'arbitrum';
    }

    /**
     * Arbitrum One nao tem moeda nativa propria - o gas e pago em ETH,
     * o mesmo ativo da Ethereum mainnet (ARB e so o token de governanca,
     * nao a moeda nativa). Por isso o simbolo repete "ETH" - e o mesmo
     * padrao que o MetaMask e outros exploradores usam.
     */
    public function symbol(): string
    {
        return 'ETH';
    }
}
