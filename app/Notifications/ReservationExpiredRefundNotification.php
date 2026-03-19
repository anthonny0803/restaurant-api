<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationExpiredRefundNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Reservation $reservation,
        private float $refundAmount,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->reservation->loadMissing('table');
        $reservation = $this->reservation;
        /** @var \App\Models\Table $table */
        $table = $reservation->table;
        $formatted = number_format($this->refundAmount, 2, ',', '.');

        return (new MailMessage())
            ->subject('Pago reembolsado - Reserva expirada')
            ->greeting("Hola, {$notifiable->name}!")
            ->line('Tu pago llego despues de que la reserva habia expirado.')
            ->line("Mesa: {$table->name}")
            ->line("Fecha: {$reservation->date->format('d/m/Y')}")
            ->line("Hora: {$reservation->start_time} - {$reservation->end_time}")
            ->line("Se ha procesado un reembolso automatico de {$formatted} EUR.")
            ->line('Si deseas, puedes realizar una nueva reserva.');
    }
}
