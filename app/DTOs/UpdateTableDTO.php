<?php

namespace App\DTOs;

readonly class UpdateTableDTO
{
    public function __construct(
        public ?string $name = null,
        public ?int $min_capacity = null,
        public ?int $max_capacity = null,
        public ?string $location = null,
        public ?string $description = null,
        public ?bool $is_active = null,
    ) {}

    public function toArray(): array
    {
        return array_filter(
            get_object_vars($this),
            fn (mixed $value) => $value !== null,
        );
    }
}
