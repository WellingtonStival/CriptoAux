<?php

namespace App\Services\Blockchain\Contracts;

/**
 * Implementada pelas blockchains que suportam tokens fungiveis dentro da
 * mesma rede (ERC-20 no Ethereum, SPL na Solana). Bitcoin nao implementa -
 * nao tem um padrao de token fungivel equivalente em uso neste sistema.
 */
interface TokenDiscoveryProvider
{
    /**
     * Descobre todos os tokens com saldo maior que zero que um endereco
     * possui. Diferente do saldo nativo, isso normalmente exige uma
     * chamada mais pesada (indexador ou varias chamadas RPC), entao nao
     * deve ser chamado a cada carregamento de tela - so quando o usuario
     * pede explicitamente pra sincronizar.
     *
     * @return array<int, array{
     *     contract_address: string,
     *     symbol: ?string,
     *     name: ?string,
     *     decimals: int,
     *     balance: float,
     * }>
     */
    public function discoverTokens(string $address): array;

    /**
     * Busca o saldo atual de um unico token ja conhecido (usado pra
     * atualizar o saldo de um token que ja foi descoberto antes, sem
     * precisar rodar a descoberta completa de novo).
     */
    public function getTokenBalance(string $address, string $contractAddress, int $decimals): float;
}
