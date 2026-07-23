<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * decimal(36,18) so permitia 18 digitos inteiros - estourou na
     * pratica com tokens de spam/scam que tem quantidade nominal
     * gigantesca (ex: quantidades na casa dos quatrilhoes). Aumentando
     * pra 50 digitos totais (32 inteiros), confortavel mesmo pra casos
     * extremos.
     */
    public function up(): void
    {
        Schema::table('wallet_token_balance_histories', function (Blueprint $table) {
            $table->decimal('balance', 50, 18)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_token_balance_histories', function (Blueprint $table) {
            $table->decimal('balance', 36, 18)->change();
        });
    }
};
