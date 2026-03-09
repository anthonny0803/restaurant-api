<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\ReservationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationItem>
 */
class ReservationItemFactory extends Factory
{
    protected $model = ReservationItem::class;

    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            'unit_price' => fake()->randomFloat(2, 3, 45),
        ];
    }
}
