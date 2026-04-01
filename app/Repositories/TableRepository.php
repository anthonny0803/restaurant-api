<?php

namespace App\Repositories;

use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TableRepository
{
    public function paginate(int $perPage = 6): LengthAwarePaginator
    {
        return Table::paginate($perPage);
    }

    public function find(int $id): ?Table
    {
        return Table::find($id);
    }

    public function findOrFail(int $id): Table
    {
        return Table::findOrFail($id);
    }

    public function create(array $data): Table
    {
        return Table::create($data);
    }

    public function update(Table $table, array $data): Table
    {
        $table->update($data);

        return $table;
    }

    public function delete(Table $table): void
    {
        $table->delete();
    }

    public function findAvailable(int $seatsRequested, string $date, string $startTime, string $endTime): Collection
    {
        return Table::where('is_active', true)
            ->where('min_capacity', '<=', $seatsRequested)
            ->where('max_capacity', '>=', $seatsRequested)
            ->whereNotIn('id', function ($query) use ($date, $startTime, $endTime) {
                $query->select('table_id')
                    ->from('reservations')
                    ->whereIn('status', [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED])
                    ->where('date', $date)
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->orderBy('max_capacity')
            ->get();
    }
}
