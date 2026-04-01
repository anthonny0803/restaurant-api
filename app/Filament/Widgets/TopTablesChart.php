<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMonthFilter;
use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class TopTablesChart extends ChartWidget
{
    use HasMonthFilter;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Mesas mas reservadas';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $byTable = app(AnalyticsService::class)
            ->occupancy($this->getMonthFilter())['by_table'];

        $tables = array_slice($byTable, 0, 5);

        return [
            'datasets' => [
                [
                    'label' => 'Reservas',
                    'data' => array_column($tables, 'total_reservations'),
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => array_column($tables, 'table_name'),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
        ];
    }
}
