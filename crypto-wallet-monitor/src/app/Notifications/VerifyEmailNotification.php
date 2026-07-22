<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends Notification
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
            ->subject('Confirme seu email - Nexfolio')
            ->line('Falta pouco! Confirme seu email para ativar sua conta na Nexfolio.')
            ->action('Confirmar email', $this->url)
            ->line('Se você não criou essa conta, pode ignorar este email.')
            ->line('Este link expira em 60 minutos.');
    }
}
