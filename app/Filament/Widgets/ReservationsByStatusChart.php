<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMonthFilter;
use App\Models\Reservation;
use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class ReservationsByStatusChart extends ChartWidget
{
    use HasMonthFilter;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Reservas por estado';

    protected ?string $maxHeight = '300px';

    private const STATUS_CONFIG = [
        Reservation::STATUS_PENDING => ['label' => 'Pendiente', 'color' => '#f59e0b'],
        Reservation::STATUS_CONFIRMED => ['label' => 'Confirmada', 'color' => '#3b82f6'],
        Reservation::STATUS_COMPLETED => ['label' => 'Completada', 'color' => '#10b981'],
        Reservation::STATUS_CANCELLED => ['label' => 'Cancelada', 'color' => '#9ca3af'],
        Reservation::STATUS_NO_SHOW => ['label' => 'No show', 'color' => '#ef4444'],
        Reservation::STATUS_EXPIRED => ['label' => 'Expirada', 'color' => '#6b7280'],
    ];

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $byStatus = app(AnalyticsService::class)
            ->occupancy($this->getMonthFilter())['by_status'];

        $labels = [];
        $data = [];
        $colors = [];

        foreach (self::STATUS_CONFIG as $status => $config) {
            $count = $byStatus[$status] ?? 0;

            if ($count === 0) {
                continue;
            }

            $labels[] = $config['label'];
            $data[] = $count;
            $colors[] = $config['color'];
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
