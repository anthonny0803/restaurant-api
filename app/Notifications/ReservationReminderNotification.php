<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationReminderNotification extends Notification implements ShouldQueue
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
            ->subject('Recordatorio de reserva')
            ->greeting("Hola, {$notifiable->name}!")
            ->line('Te recordamos que tienes una reserva proxima.')
            ->line("Mesa: {$table->name}")
            ->line("Fecha: {$reservation->date->format('d/m/Y')}")
            ->line("Hora: {$reservation->start_time} - {$reservation->end_time}")
            ->line("Comensales: {$reservation->seats_requested}")
            ->line('Te esperamos!');
    }
}
