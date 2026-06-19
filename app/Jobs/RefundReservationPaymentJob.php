<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\Reservation;
use App\Notifications\ReservationCancelledNotification;
use App\Repositories\PaymentRepository;
use App\Repositories\ReservationRepository;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefundReservationPaymentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(private int $reservationId) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(ReservationRepository $reservationRepository, PaymentService $paymentService): void
    {
        $refundAmount = DB::transaction(function () use ($reservationRepository) {
            $reservation = $reservationRepository->lockById($this->reservationId);

            if (! $reservation || $reservation->status !== Reservation::STATUS_CANCELLED) {
                return null;
            }

            $payment = $reservation->payment;

            if (! $payment || $payment->status !== Payment::STATUS_REFUND_PENDING) {
                return null;
            }

            return (float) $payment->refund_amount;
        });

        if ($refundAmount === null) {
            return;
        }

        $reservation = $reservationRepository->find($this->reservationId);

        $paymentService->refund($reservation->payment, $refundAmount);

        $reservation->user?->notify(new ReservationCancelledNotification($reservation, $refundAmount));
    }

    public function failed(Throwable $exception): void
    {
        $reservationRepository = app(ReservationRepository::class);
        $reservation = $reservationRepository->find($this->reservationId);

        if (! $reservation) {
            return;
        }

        $payment = $reservation->payment;

        if ($payment && $payment->status === Payment::STATUS_REFUND_PENDING) {
            app(PaymentRepository::class)->update($payment, [
                'status' => Payment::STATUS_REFUND_FAILED,
            ]);
        }

        Log::error('Refund permanently failed on reservation cancellation', [
            'reservation_id' => $this->reservationId,
            'payment_id' => $payment?->id,
            'gateway_id' => $payment?->payment_gateway_id,
            'amount' => $payment?->refund_amount,
            'exception' => $exception->getMessage(),
        ]);

        $reservation->user?->notify(new ReservationCancelledNotification($reservation, null));
    }
}
