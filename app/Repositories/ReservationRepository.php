<?php

namespace App\Repositories;

use App\Models\CancellationPolicySnapshot;
use App\Models\Reservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ReservationRepository
{
    public function create(array $data): Reservation
    {
        return Reservation::create($data);
    }

    public function find(int $id): ?Reservation
    {
        return Reservation::find($id);
    }

    public function createSnapshot(Reservation $reservation, array $data): CancellationPolicySnapshot
    {
        return $reservation->cancellationPolicySnapshot()->create($data);
    }

    public function hasOverlappingReservation(
        int $tableId,
        string $date,
        string $startTime,
        string $endTime,
    ): bool {
        return Reservation::active()
            ->forTable($tableId)
            ->forDate($date)
            ->overlapping($startTime, $endTime)
            ->exists();
    }

    public function hasPendingReservation(int $userId): bool
    {
        return Reservation::pending()
            ->where('user_id', $userId)
            ->exists();
    }

    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Reservation::where('user_id', $userId)
            ->latest()
            ->paginate($perPage);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Reservation::latest()
            ->paginate($perPage);
    }

    public function findExpired(): Collection
    {
        return Reservation::expired()->get();
    }

    public function updateStatus(Reservation $reservation, string $status): Reservation
    {
        $reservation->update(['status' => $status]);

        return $reservation;
    }
}
