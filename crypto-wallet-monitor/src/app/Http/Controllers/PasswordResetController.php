<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Sempre retorna a mesma mensagem, exista ou nao o email, para nao
        // revelar quais emails estao cadastrados no sistema.
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Se o email estiver cadastrado, enviamos um link de redefinição.',
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $validated,
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Link inválido ou expirado. Solicite um novo.',
            ], 422);
        }

        return response()->json([
            'message' => 'Senha redefinida com sucesso.',
        ]);
    }
}
