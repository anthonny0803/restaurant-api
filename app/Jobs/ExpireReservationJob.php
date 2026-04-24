<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\Reservation;
use App\Notifications\ReservationExpiredNotification;
use App\Repositories\ReservationRepository;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ExpireReservationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private int $reservationId) {}

    public function handle(ReservationRepository $reservationRepository, PaymentService $paymentService): void
    {
        $expiredNow = DB::transaction(function () use ($reservationRepository) {
            $reservation = $reservationRepository->lockById($this->reservationId);

            if (! $reservation || $reservation->status !== Reservation::STATUS_PENDING) {
                return false;
            }

            $reservationRepository->updateStatus($reservation, Reservation::STATUS_EXPIRED);

            return true;
        });

        $reservation = $reservationRepository->find($this->reservationId);

        if (! $reservation) {
            return;
        }

        if ($expiredNow && $reservation->user) {
            $reservation->user->notify(new ReservationExpiredNotification($reservation));
        }

        if ($reservation->status !== Reservation::STATUS_EXPIRED) {
            return;
        }

        $payment = $reservation->payment;

        if ($payment && $payment->status === Payment::STATUS_PENDING) {
            $paymentService->cancelPaymentIntent($payment);
        }
    }
}
