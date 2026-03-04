<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $user->hasRole('admin') || $user->id === $reservation->user_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('client');
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return $user->hasRole('admin') || $user->id === $reservation->user_id;
    }
}
