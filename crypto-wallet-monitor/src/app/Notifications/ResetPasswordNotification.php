<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    public function __construct(private string $url)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Redefinição de senha - Crypto Wallet Monitor')
            ->line('Você solicitou a redefinição da sua senha.')
            ->action('Redefinir senha', $this->url)
            ->line('Se você não solicitou isso, pode ignorar este email — sua senha continua a mesma.')
            ->line('Este link expira em 60 minutos.');
    }
}
