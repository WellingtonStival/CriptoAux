<?php

namespace App\Services\Blockchain\Contracts;

interface BlockchainServiceInterface
{
    public function getBalance(string $address): float;
    public function symbol(): string;
}
