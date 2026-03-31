<?php

namespace App\Filament\Widgets\Concerns;

use App\DTOs\AnalyticsFilterDTO;
use Carbon\Carbon;

trait HasMonthFilter
{
    protected function getFilters(): array
    {
        $filters = [];

        for ($i = 0; $i < 6; $i++) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');
            $filters[$key] = $date->translatedFormat('F Y');
        }

        return $filters;
    }

    protected function getMonthFilter(): AnalyticsFilterDTO
    {
        $month = $this->filter ?? now()->format('Y-m');
        $date = Carbon::createFromFormat('Y-m', $month);

        return new AnalyticsFilterDTO(
            date_from: $date->startOfMonth()->format('Y-m-d'),
            date_to: $date->endOfMonth()->format('Y-m-d'),
        );
    }
}
