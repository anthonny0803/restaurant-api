<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Reservation $reservation,
        private ?float $refundAmount = null,
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

        $mail = (new MailMessage())
            ->subject('Reserva cancelada')
            ->greeting("Hola, {$notifiable->name}!")
            ->line('Tu reserva ha sido cancelada.')
            ->line("Mesa: {$table->name}")
            ->line("Fecha: {$reservation->date->format('d/m/Y')}")
            ->line("Hora: {$reservation->start_time} - {$reservation->end_time}");

        if ($this->refundAmount !== null) {
            $formatted = number_format($this->refundAmount, 2, ',', '.');
            $mail->line("Se ha procesado un reembolso de {$formatted} EUR.");
        }

        return $mail;
    }
}
