<?php

namespace App\Repositories;

use App\Models\ReservationItem;

class ReservationItemRepository
{
    public function create(array $data): ReservationItem
    {
        return ReservationItem::create($data);
    }

    public function delete(ReservationItem $item): void
    {
        $item->delete();
    }
}
