<?php

namespace App\Filament\Widgets;

use App\DTOs\AnalyticsFilterDTO;
use App\Models\User;
use App\Services\AnalyticsService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Resumen del mes';

    protected function getStats(): array
    {
        $analytics = app(AnalyticsService::class);
        $filter = new AnalyticsFilterDTO(
            date_from: now()->startOfMonth()->format('Y-m-d'),
            date_to: now()->endOfMonth()->format('Y-m-d'),
        );

        $occupancy = $analytics->occupancy($filter);
        $totalUsers = User::count();

        return [
            Stat::make('Reservas del mes', $occupancy['total_reservations'])
                ->icon(Heroicon::OutlinedCalendarDays),

            Stat::make('Usuarios registrados', $totalUsers)
                ->icon(Heroicon::OutlinedUserGroup),
        ];
    }
}
