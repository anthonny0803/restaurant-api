<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Notifications\ReservationExpiredNotification;
use App\Repositories\ReservationRepository;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireReservationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private int $reservationId) {}

    public function handle(ReservationRepository $reservationRepository, PaymentService $paymentService): void
    {
        $reservation = $reservationRepository->find($this->reservationId);

        if (! $reservation || $reservation->status !== Reservation::STATUS_PENDING) {
            return;
        }

        $reservationRepository->updateStatus($reservation, Reservation::STATUS_EXPIRED);

        $payment = $reservation->payment;

        if ($payment) {
            $paymentService->cancelPaymentIntent($payment);
        }

        if ($reservation->user) {
            $reservation->user->notify(new ReservationExpiredNotification($reservation));
        }
    }
}
