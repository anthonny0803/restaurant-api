<?php

namespace App\Repositories;

use App\Models\Reservation;
use App\Models\ReservationItem;
use Illuminate\Database\Eloquent\Collection;

class ReservationItemRepository
{
    public function listForReservation(Reservation $reservation): Collection
    {
        return $reservation->reservationItems()->with('menuItem')->get();
    }

    public function create(array $data): ReservationItem
    {
        return ReservationItem::create($data);
    }

    public function delete(ReservationItem $item): void
    {
        $item->delete();
    }
}
