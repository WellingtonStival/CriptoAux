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
        Schema::create('wallet_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('contract_address');
            $table->string('symbol')->nullable();
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('decimals')->default(18);
            $table->timestamps();

            $table->unique(['wallet_id', 'contract_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_tokens');
    }
};
