<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Reservation $reservation)
    {
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

        return (new MailMessage())
            ->subject('Reserva expirada')
            ->greeting("Hola, {$notifiable->name}!")
            ->line('Tu reserva ha expirado porque no se completo el pago a tiempo.')
            ->line("Mesa: {$table->name}")
            ->line("Fecha: {$reservation->date->format('d/m/Y')}")
            ->line("Hora: {$reservation->start_time} - {$reservation->end_time}")
            ->line('Si deseas, puedes realizar una nueva reserva.');
    }
}
