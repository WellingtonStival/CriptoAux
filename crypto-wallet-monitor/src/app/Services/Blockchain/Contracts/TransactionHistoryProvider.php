<?php

namespace App\Services\Blockchain\Contracts;

/**
 * Implementada apenas pelas blockchains que ja tem uma fonte de dados pronta
 * para listar transacoes por endereco (Solana e Bitcoin, via RPC/API que ja
 * usamos para o saldo). Ethereum ainda nao implementa: a RPC publica usada
 * para saldo nao lista transacoes, isso exigiria uma API indexadora
 * separada (ex: Etherscan, que pede chave de API).
 */
interface TransactionHistoryProvider
{
    /**
     * Retorna as ultimas transacoes de um endereco, mais recentes primeiro.
     *
     * @return array<int, array{
     *     hash: string,
     *     direction: 'in'|'out',
     *     amount: float,
     *     timestamp: ?string,
     *     explorer_url: string,
     * }>
     */
    public function getTransactions(string $address, int $limit = 10): array;
}
