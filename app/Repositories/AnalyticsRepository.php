<?php

namespace App\Repositories;

use App\DTOs\AnalyticsFilterDTO;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsRepository
{
    public function reservationsByStatus(AnalyticsFilterDTO $filter): Collection
    {
        return DB::table('reservations')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->tap(fn ($q) => $this->applyDateFilter($q, $filter))
            ->groupBy('status')
            ->get();
    }

    public function occupancyByTable(AnalyticsFilterDTO $filter): Collection
    {
        return DB::table('reservations')
            ->join('tables', 'reservations.table_id', '=', 'tables.id')
            ->select(
                'tables.name as table_name',
                'tables.max_capacity',
                DB::raw('COUNT(*) as total_reservations'),
                DB::raw('AVG(reservations.seats_requested) as avg_seats'),
            )
            ->whereIn('reservations.status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_COMPLETED,
            ])
            ->tap(fn ($q) => $this->applyDateFilter($q, $filter, 'reservations.date'))
            ->groupBy('tables.id', 'tables.name', 'tables.max_capacity')
            ->orderByDesc('total_reservations')
            ->get();
    }

    public function peakDays(AnalyticsFilterDTO $filter, int $limit = 5): Collection
    {
        return DB::table('reservations')
            ->select('date', DB::raw('COUNT(*) as total_reservations'))
            ->whereIn('status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_COMPLETED,
            ])
            ->tap(fn ($q) => $this->applyDateFilter($q, $filter))
            ->groupBy('date')
            ->orderByDesc('total_reservations')
            ->limit($limit)
            ->get();
    }

    public function peakHours(AnalyticsFilterDTO $filter, int $limit = 5): Collection
    {
        return DB::table('reservations')
            ->select(
                DB::raw('EXTRACT(HOUR FROM start_time)::integer as hour'),
                DB::raw('COUNT(*) as total_reservations'),
            )
            ->whereIn('status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_COMPLETED,
            ])
            ->tap(fn ($q) => $this->applyDateFilter($q, $filter))
            ->groupBy('hour')
            ->orderByDesc('total_reservations')
            ->limit($limit)
            ->get();
    }

    public function averageSeatsPerReservation(AnalyticsFilterDTO $filter): float
    {
        return (float) (DB::table('reservations')
            ->whereIn('status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_COMPLETED,
            ])
            ->tap(fn ($q) => $this->applyDateFilter($q, $filter))
            ->avg('seats_requested') ?? 0);
    }

    public function revenueTotals(AnalyticsFilterDTO $filter): object
    {
        return DB::table('payments')
            ->join('reservations', 'payments.reservation_id', '=', 'reservations.id')
            ->select(
                DB::raw('COALESCE(SUM(payments.amount), 0) as total_collected'),
                DB::raw('COALESCE(SUM(payments.refund_amount), 0) as total_refunded'),
                DB::raw('COUNT(*) as total_payments'),
            )
            ->whereIn('payments.status', [
                Payment::STATUS_SUCCEEDED,
                Payment::STATUS_REFUNDED,
                Payment::STATUS_PARTIALLY_REFUNDED,
            ])
            ->tap(fn ($q) => $this->applyDateFilter($q, $filter, 'reservations.date'))
            ->first();
    }

    public function topItemsByQuantity(AnalyticsFilterDTO $filter, int $limit = 10): Collection
    {
        return DB::table('reservation_items')
            ->join('menu_items', 'reservation_items.menu_item_id', '=', 'menu_items.id')
            ->join('reservations', 'reservation_items.reservation_id', '=', 'reservations.id')
            ->select(
                'menu_items.name',
                'menu_items.category',
                DB::raw('SUM(reservation_items.quantity) as total_quantity'),
            )
            ->whereIn('reservations.status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_COMPLETED,
            ])
            ->tap(fn ($q) => $this->applyDateFilter($q, $filter, 'reservations.date'))
            ->groupBy('menu_items.id', 'menu_items.name', 'menu_items.category')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();
    }

    public function topItemsByRevenue(AnalyticsFilterDTO $filter, int $limit = 10): Collection
    {
        return DB::table('reservation_items')
            ->join('menu_items', 'reservation_items.menu_item_id', '=', 'menu_items.id')
            ->join('reservations', 'reservation_items.reservation_id', '=', 'reservations.id')
            ->select(
                'menu_items.name',
                'menu_items.category',
                DB::raw('SUM(reservation_items.quantity * reservation_items.unit_price) as total_revenue'),
            )
            ->whereIn('reservations.status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_COMPLETED,
            ])
            ->tap(fn ($q) => $this->applyDateFilter($q, $filter, 'reservations.date'))
            ->groupBy('menu_items.id', 'menu_items.name', 'menu_items.category')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();
    }

    public function revenueByCategory(AnalyticsFilterDTO $filter): Collection
    {
        return DB::table('reservation_items')
            ->join('menu_items', 'reservation_items.menu_item_id', '=', 'menu_items.id')
            ->join('reservations', 'reservation_items.reservation_id', '=', 'reservations.id')
            ->select(
                'menu_items.category',
                DB::raw('SUM(reservation_items.quantity) as total_quantity'),
                DB::raw('SUM(reservation_items.quantity * reservation_items.unit_price) as total_revenue'),
            )
            ->whereIn('reservations.status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_COMPLETED,
            ])
            ->tap(fn ($q) => $this->applyDateFilter($q, $filter, 'reservations.date'))
            ->groupBy('menu_items.category')
            ->orderByDesc('total_revenue')
            ->get();
    }

    private function applyDateFilter(Builder $query, AnalyticsFilterDTO $filter, string $column = 'date'): void
    {
        $query
            ->when($filter->date_from, fn ($q, $date) => $q->where($column, '>=', $date))
            ->when($filter->date_to, fn ($q, $date) => $q->where($column, '<=', $date));
    }
}
