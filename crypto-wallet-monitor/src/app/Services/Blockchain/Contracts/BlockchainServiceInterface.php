<?php

namespace App\Services\Blockchain\Contracts;

interface BlockchainServiceInterface
{
    /**
     * Busca o saldo ao vivo na blockchain (bloqueante). Usada pelo job de
     * atualizacao em background e por consultas explicitas do usuario.
     * $forceRefresh ignora um cache ainda valido e busca ao vivo mesmo
     * assim (usado pelo botao manual de atualizar saldo).
     */
    public function getBalance(string $address, bool $forceRefresh = false): float;

    /**
     * Le o saldo do cache sem nunca chamar a blockchain. Retorna null em
     * caso de cache frio - quem chamar decide o que fazer (servir o ultimo
     * saldo salvo, despachar um refresh em background, etc.).
     */
    public function getCachedBalance(string $address): ?float;

    public function symbol(): string;

    /**
     * Expressao regular usada para validar o formato de endereco desta rede.
     */
    public function addressPattern(): string;
}
