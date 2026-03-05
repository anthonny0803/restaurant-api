<?php

namespace App\DTOs;

use App\Enums\MenuCategory;

readonly class StoreMenuItemDTO
{
    public function __construct(
        public string $name,
        public float $price,
        public MenuCategory $category,
        public ?string $description = null,
        public bool $is_available = true,
        public ?int $daily_stock = null,
    ) {}
}
