<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Repositories\ReservationRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoCompleteReservationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ReservationRepository $reservationRepository): void
    {
        $reservations = $reservationRepository->findCompletable();

        foreach ($reservations as $reservation) {
            $reservationRepository->updateStatus($reservation, Reservation::STATUS_COMPLETED);
        }
    }
}
