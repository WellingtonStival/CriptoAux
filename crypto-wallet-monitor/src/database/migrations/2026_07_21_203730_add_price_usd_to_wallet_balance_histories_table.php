<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wallet_balance_histories', function (Blueprint $table) {
            $table->decimal('price_usd', 20, 8)->nullable()->after('balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_balance_histories', function (Blueprint $table) {
            $table->dropColumn('price_usd');
        });
    }
};
