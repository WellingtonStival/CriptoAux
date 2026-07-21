<?php

namespace App\Services\Blockchain\Contracts;

interface BlockchainServiceInterface
{
    public function getBalance(string $address): float;
    public function symbol(): string;

    /**
     * Expressao regular usada para validar o formato de endereco desta rede.
     */
    public function addressPattern(): string;
}
