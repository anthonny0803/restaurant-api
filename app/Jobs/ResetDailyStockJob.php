<?php

namespace App\Jobs;

use App\Repositories\MenuItemRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ResetDailyStockJob implements ShouldQueue
{
    use Queueable;

    public function handle(MenuItemRepository $menuItemRepository): void
    {
        $menuItemRepository->resetDailyStock();
    }
}
