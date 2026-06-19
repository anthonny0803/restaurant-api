<?php

namespace Tests\Feature;

use App\Jobs\ResetDailyStockJob;
use App\Models\MenuItem;
use App\Repositories\MenuItemRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetDailyStockJobTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(): void
    {
        (new ResetDailyStockJob())->handle(
            app(MenuItemRepository::class),
        );
    }

    public function test_resets_partially_consumed_stock_to_quota(): void
    {
        $menuItem = MenuItem::factory()->create([
            'daily_stock' => 10,
            'stock_remaining' => 3,
        ]);

        $this->runJob();

        $this->assertSame(10, $menuItem->fresh()->stock_remaining);
    }

    public function test_leaves_unlimited_items_untouched(): void
    {
        $menuItem = MenuItem::factory()->unlimitedStock()->create();

        $this->runJob();

        $this->assertNull($menuItem->fresh()->stock_remaining);
    }

    public function test_keeps_full_stock_unchanged(): void
    {
        $menuItem = MenuItem::factory()->create(['daily_stock' => 15]);

        $this->runJob();

        $this->assertSame(15, $menuItem->fresh()->stock_remaining);
    }

    public function test_resets_multiple_items_in_single_run(): void
    {
        $first = MenuItem::factory()->create(['daily_stock' => 10, 'stock_remaining' => 0]);
        $second = MenuItem::factory()->create(['daily_stock' => 20, 'stock_remaining' => 5]);

        $this->runJob();

        $this->assertSame(10, $first->fresh()->stock_remaining);
        $this->assertSame(20, $second->fresh()->stock_remaining);
    }
}
