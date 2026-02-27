<?php

namespace App\DTOs;

readonly class StoreTableDTO
{
    public function __construct(
        public string $name,
        public int $min_capacity,
        public int $max_capacity,
        public string $location,
        public ?string $description = null,
        public bool $is_active = true,
    ) {}
}
