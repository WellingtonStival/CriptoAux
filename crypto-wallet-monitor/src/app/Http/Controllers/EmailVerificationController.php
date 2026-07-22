<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmailVerificationController extends Controller
{
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->email_verification_token
            || !Hash::check($validated['token'], $user->email_verification_token)) {
            return response()->json([
                'message' => 'Link inválido ou expirado. Solicite um novo.',
            ], 422);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_token' => null,
        ])->save();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Email confirmado com sucesso.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Reenvia o link de confirmacao. Sempre retorna a mesma mensagem,
     * exista ou nao o email (ou ja esteja verificado), pelo mesmo motivo
     * do PasswordResetController: nao revelar quais emails estao
     * cadastrados no sistema.
     */
    public function resend(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user && !$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'Se o email estiver cadastrado e pendente de confirmação, reenviamos o link.',
        ]);
    }
}
