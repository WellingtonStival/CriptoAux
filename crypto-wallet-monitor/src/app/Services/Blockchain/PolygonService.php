<?php

namespace App\Services\Blockchain;

class PolygonService extends AbstractEvmChainService
{
    protected function network(): string
    {
        return 'polygon';
    }

    public function symbol(): string
    {
        return 'POL';
    }
}
