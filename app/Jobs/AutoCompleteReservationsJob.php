<?php

namespace App\Jobs;

use App\Repositories\ReservationRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AutoCompleteReservationsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ReservationRepository $reservationRepository): void
    {
        $reservationRepository->completeDue();
    }
}
