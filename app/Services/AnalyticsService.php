<?php

namespace App\Services;

use App\DTOs\AnalyticsFilterDTO;
use App\Repositories\AnalyticsRepository;

class AnalyticsService
{
    public function __construct(private AnalyticsRepository $repository) {}

    public function occupancy(AnalyticsFilterDTO $filter): array
    {
        $byStatus = $this->repository->reservationsByStatus($filter);
        $totalReservations = $byStatus->sum('total');

        $byTable = $this->repository->occupancyByTable($filter);
        $peakDays = $this->repository->peakDays($filter);
        $peakHours = $this->repository->peakHours($filter);
        $averageSeats = $this->repository->averageSeatsPerReservation($filter);

        return [
            'total_reservations' => $totalReservations,
            'by_status' => $byStatus->pluck('total', 'status'),
            'average_seats_per_reservation' => round($averageSeats, 1),
            'by_table' => $byTable->map(fn ($table) => [
                'table_name' => $table->table_name,
                'total_reservations' => $table->total_reservations,
                'occupancy_rate' => $table->max_capacity > 0
                    ? round(($table->avg_seats / $table->max_capacity) * 100, 1)
                    : 0,
            ])->values()->toArray(),
            'peak_days' => $peakDays->map(fn ($day) => [
                'date' => $day->date,
                'total_reservations' => $day->total_reservations,
            ])->toArray(),
            'peak_hours' => $peakHours->map(fn ($hour) => [
                'hour' => $hour->hour,
                'total_reservations' => $hour->total_reservations,
            ])->toArray(),
        ];
    }

    public function revenue(AnalyticsFilterDTO $filter): array
    {
        $totals = $this->repository->revenueTotals($filter);

        $collected = (float) $totals->total_collected;
        $refunded = (float) $totals->total_refunded;

        return [
            'total_collected' => round($collected, 2),
            'total_refunded' => round($refunded, 2),
            'net_revenue' => round($collected - $refunded, 2),
            'total_payments' => (int) $totals->total_payments,
            'average_deposit' => $totals->total_payments > 0
                ? round($collected / $totals->total_payments, 2)
                : 0,
        ];
    }

    public function topMenuItems(AnalyticsFilterDTO $filter): array
    {
        $byQuantity = $this->repository->topItemsByQuantity($filter);
        $byRevenue = $this->repository->topItemsByRevenue($filter);
        $byCategory = $this->repository->revenueByCategory($filter);

        return [
            'top_by_quantity' => $byQuantity->map(fn ($item) => [
                'menu_item' => $item->name,
                'category' => $item->category,
                'total_quantity' => (int) $item->total_quantity,
            ])->toArray(),
            'top_by_revenue' => $byRevenue->map(fn ($item) => [
                'menu_item' => $item->name,
                'category' => $item->category,
                'total_revenue' => round((float) $item->total_revenue, 2),
            ])->toArray(),
            'by_category' => $byCategory->map(fn ($cat) => [
                'category' => $cat->category,
                'total_quantity' => (int) $cat->total_quantity,
                'total_revenue' => round((float) $cat->total_revenue, 2),
            ])->toArray(),
        ];
    }
}
