<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Restablecer contrasena')
            ->greeting("Hola, {$notifiable->name}!")
            ->line('Recibimos una solicitud para restablecer la contrasena de tu cuenta.')
            ->line('Tu codigo de restablecimiento es:')
            ->line($this->token)
            ->line('Este codigo expira en 60 minutos.')
            ->line('Si no solicitaste este cambio, puedes ignorar este correo. Tu contrasena no sera modificada.');
    }
}
