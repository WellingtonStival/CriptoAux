<?php

namespace App\Services\Blockchain;

class BnbService extends AbstractEvmChainService
{
    protected function network(): string
    {
        return 'bnb';
    }

    public function symbol(): string
    {
        return 'BNB';
    }
}
