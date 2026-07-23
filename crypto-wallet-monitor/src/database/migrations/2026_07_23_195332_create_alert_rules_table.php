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
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // wallet_balance_drop | portfolio_change | price_change
            $table->string('type');

            // Só usado em wallet_balance_drop - null = qualquer wallet do usuario.
            $table->foreignId('wallet_id')->nullable()->constrained()->cascadeOnDelete();

            // So usado em price_change - chave de rede (ex: "bitcoin").
            $table->string('network')->nullable();

            $table->decimal('threshold_percent', 6, 2);

            // down | up | any - wallet_balance_drop so faz sentido "down",
            // mas price_change/portfolio_change podem ser qualquer direcao.
            $table->string('direction')->default('down');

            $table->boolean('is_active')->default(true);

            // Evita spam: so dispara de novo depois de um tempo minimo
            // (ver AlertEvaluationService::DEBOUNCE_HOURS).
            $table->timestamp('last_triggered_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
