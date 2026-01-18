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
		Schema::create('wallet_balance_histories', function (Blueprint $table) {
			$table->id();
			$table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
			$table->string('network');
			$table->decimal('balance', 20, 8);
			$table->timestamp('captured_at');
			$table->timestamps();

			$table->index(['wallet_id', 'captured_at']);
		});
	}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_balance_histories');
    }
};
