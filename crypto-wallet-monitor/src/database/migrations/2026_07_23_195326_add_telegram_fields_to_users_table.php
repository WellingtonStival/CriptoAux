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
        Schema::table('users', function (Blueprint $table) {
            // Preenchido quando o usuario conclui o /start no bot - null = nao conectado.
            $table->string('telegram_chat_id')->nullable()->unique()->after('password');
            // Codigo temporario gerado ao clicar "Conectar Telegram", usado pra
            // casar a mensagem /start recebida com o usuario certo. Limpo
            // assim que o link e concluido.
            $table->string('telegram_link_code')->nullable()->unique()->after('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_link_code']);
        });
    }
};
