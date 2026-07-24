<?php

namespace App\Services\Blockchain;

class AvalancheService extends AbstractEvmChainService
{
    protected function network(): string
    {
        return 'avalanche';
    }

    public function symbol(): string
    {
        return 'AVAX';
    }
}
