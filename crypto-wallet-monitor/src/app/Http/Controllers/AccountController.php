<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'telegram_linked' => $user->hasTelegramLinked(),
            'created_at' => $user->created_at->toIso8601String(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update($validated);

        return response()->json(['message' => 'Nome atualizado.']);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Senha atual incorreta.',
                'errors' => ['current_password' => ['Senha atual incorreta.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['message' => 'Senha atualizada.']);
    }

    /**
     * Exclui a conta permanentemente. Exige a senha de novo (nao basta
     * estar logado) - e a unica acao irreversivel que o proprio usuario
     * pode disparar no sistema, entao vale a camada extra de confirmacao.
     * As foreign keys de wallets/alert_rules/etc ja sao ON DELETE CASCADE,
     * entao apagar o usuario limpa tudo sozinho.
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Senha incorreta.',
                'errors' => ['password' => ['Senha incorreta.']],
            ], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Conta excluída.']);
    }
}
