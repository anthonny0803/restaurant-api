<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationPaymentRefundedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Reservation $reservation,
        private float $refundAmount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->reservation->loadMissing('table');
        $reservation = $this->reservation;
        $table = $reservation->table;
        $formatted = number_format($this->refundAmount, 2, ',', '.');

        return (new MailMessage())
            ->subject('Reembolso procesado por tu reserva')
            ->greeting("Hola, {$notifiable->name}!")
            ->line('Recibimos tu pago, pero no pudimos completar tu reserva porque ya no se encontraba activa al momento de procesarlo.')
            ->line("Mesa: {$table->name}")
            ->line("Fecha: {$reservation->date->format('d/m/Y')}")
            ->line("Hora: {$reservation->start_time} - {$reservation->end_time}")
            ->line("Hemos procesado un reembolso completo de {$formatted} EUR de forma automatica. Deberia reflejarse en tu cuenta en los proximos dias habiles.")
            ->line('Si deseas hacer una nueva reserva, puedes hacerlo desde la aplicacion.');
    }
}
