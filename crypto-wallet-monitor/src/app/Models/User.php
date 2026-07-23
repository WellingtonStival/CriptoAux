<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Wallet;
use App\Models\AlertRule;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'telegram_chat_id',
        'telegram_link_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
	
	public function wallets()
	{
		return $this->hasMany(Wallet::class);
	}

	public function alertRules()
	{
		return $this->hasMany(AlertRule::class);
	}

	public function hasTelegramLinked(): bool
	{
		return $this->telegram_chat_id !== null;
	}

	/**
	 * Sobrescreve o comportamento padrao do Laravel (que linkaria pra uma
	 * rota web que nao existe nesta API) para apontar pro frontend React.
	 */
	public function sendPasswordResetNotification($token): void
	{
		$url = rtrim(config('frontend.url'), '/')
			. '/redefinir-senha?token=' . $token
			. '&email=' . urlencode($this->email);

		$this->notify(new ResetPasswordNotification($url));
	}

	public function hasVerifiedEmail(): bool
	{
		return $this->email_verified_at !== null;
	}

	/**
	 * Gera um novo token de verificacao, salva o hash (nunca o valor em
	 * texto puro, mesmo raciocinio do password_reset_tokens do Laravel) e
	 * envia o email com o link pro frontend. Retorna o token em texto
	 * puro so pra quem chamou usar em testes, se precisar.
	 *
	 * O envio do email fica dentro de um try/catch de proposito: uma
	 * falha no provedor de email (fora do ar, sandbox do Resend recusando
	 * o destinatario, etc.) nao pode derrubar o cadastro inteiro - a
	 * conta ja foi criada no banco nesse ponto. Sem isso, o usuario via
	 * um erro 500 mesmo com a conta ja existindo, sem conseguir nem
	 * pedir o link de novo (mesmo problema apareceria no reenvio).
	 */
	public function sendEmailVerificationNotification(): string
	{
		$plainToken = Str::random(64);

		$this->forceFill([
			'email_verification_token' => Hash::make($plainToken),
		])->save();

		$url = rtrim(config('frontend.url'), '/')
			. '/verificar-email?token=' . $plainToken
			. '&email=' . urlencode($this->email);

		try {
			$this->notify(new VerifyEmailNotification($url));
		} catch (Throwable $exception) {
			Log::warning('Falha ao enviar email de verificacao', [
				'user_id' => $this->id,
				'error' => $exception->getMessage(),
			]);
		}

		return $plainToken;
	}
}
