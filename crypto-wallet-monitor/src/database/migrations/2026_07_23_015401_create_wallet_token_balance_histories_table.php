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
        Schema::create('wallet_token_balance_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_token_id')->constrained()->cascadeOnDelete();
            $table->decimal('balance', 36, 18);
            $table->decimal('price_usd', 20, 8)->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_token_balance_histories');
    }
};
