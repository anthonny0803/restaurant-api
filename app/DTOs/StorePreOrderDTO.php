<?php

namespace App\DTOs;

readonly class StorePreOrderDTO
{
    public function __construct(
        public int $menu_item_id,
        public int $quantity,
    ) {}
}
