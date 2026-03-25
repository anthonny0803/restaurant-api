<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCompletionNotification extends Notification implements ShouldQueue
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
            ->subject('Completa tu cuenta')
            ->greeting("Hola, {$notifiable->name}!")
            ->line('Tienes una cuenta pendiente de completar. Establece una contrasena para acceder a todas las funcionalidades.')
            ->line('Tu codigo para establecer tu contrasena es:')
            ->line($this->token)
            ->line('Este codigo expira en 60 minutos.')
            ->line('Si no solicitaste esto, puedes ignorar este correo.');
    }
}
