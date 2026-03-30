<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuestReservationConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Reservation $reservation,
        private string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->reservation->loadMissing(['table', 'cancellationPolicySnapshot']);
        $reservation = $this->reservation;
        /** @var \App\Models\Table $table */
        $table = $reservation->table;
        /** @var \App\Models\CancellationPolicySnapshot $policy */
        $policy = $reservation->cancellationPolicySnapshot;

        return (new MailMessage())
            ->subject('Reserva confirmada')
            ->greeting("Hola, {$notifiable->name}!")
            ->line('Tu reserva ha sido confirmada exitosamente.')
            ->line("Mesa: {$table->name}")
            ->line("Fecha: {$reservation->date->format('d/m/Y')}")
            ->line("Hora: {$reservation->start_time} - {$reservation->end_time}")
            ->line("Comensales: {$reservation->seats_requested}")
            ->line('---')
            ->line('Politica de cancelacion:')
            ->line("- Si cancelas con mas de {$policy->cancellation_deadline_hours} horas de antelacion, recibiras un reembolso completo.")
            ->line("- Si cancelas con menos de {$policy->cancellation_deadline_hours} horas de antelacion, recibiras un reembolso del {$policy->refund_percentage}% del deposito.")
            ->line('---')
            ->line('Tu token de acceso para gestionar tu reserva:')
            ->line($this->token)
            ->line('Con este token puedes consultar tu reserva, hacer pre-pedidos o cancelar. Incluyelo como cabecera Authorization: Bearer {token} en tus peticiones.')
            ->line('---')
            ->line('Para completar tu cuenta y establecer una contrasena, usa el endpoint POST /auth/complete-account con tu token de acceso.')
            ->line('Te esperamos!');
    }
}
