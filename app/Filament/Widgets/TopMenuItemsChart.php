<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMonthFilter;
use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class TopMenuItemsChart extends ChartWidget
{
    use HasMonthFilter;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Platos mas pedidos';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $topItems = app(AnalyticsService::class)
            ->topMenuItems($this->getMonthFilter())['top_by_quantity'];

        $items = array_slice($topItems, 0, 5);

        return [
            'datasets' => [
                [
                    'label' => 'Cantidad pedida',
                    'data' => array_column($items, 'total_quantity'),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => array_column($items, 'menu_item'),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
        ];
    }
}
