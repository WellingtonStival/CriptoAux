<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O mesmo endereco 0x... e o mesmo em qualquer rede EVM (Ethereum,
     * Polygon, BNB Chain - mesma chave privada, mesmo endereco). Com
     * "address" unico globalmente, era impossivel rastrear a mesma
     * carteira em mais de uma rede EVM - bug real, pego ao tentar
     * cadastrar um endereco ja usado no Ethereum tambem na Polygon.
     * A unicidade que faz sentido e por (address, network).
     */
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique('wallets_address_unique');
            $table->unique(['address', 'network']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique(['address', 'network']);
            $table->unique('address');
        });
    }
};
