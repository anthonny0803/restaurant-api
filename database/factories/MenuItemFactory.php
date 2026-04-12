<?php

namespace Database\Factories;

use App\Enums\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 3, 45),
            'category' => fake()->randomElement(MenuCategory::cases()),
            'is_available' => true,
            'daily_stock' => fake()->optional(0.7)->numberBetween(5, 50),
        ];
    }

    public function unavailable(): static
    {
        return $this->state(['is_available' => false]);
    }

    public function withoutStock(): static
    {
        return $this->state(['daily_stock' => 0]);
    }

    public function unlimitedStock(): static
    {
        return $this->state(['daily_stock' => null]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }
}
