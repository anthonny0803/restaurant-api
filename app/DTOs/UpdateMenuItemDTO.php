<?php

namespace App\DTOs;

use App\Enums\MenuCategory;

readonly class UpdateMenuItemDTO
{
    public function __construct(
        public ?string $name = null,
        public ?float $price = null,
        public ?MenuCategory $category = null,
        public ?string $description = null,
        public ?bool $is_available = null,
        public ?int $daily_stock = null,
        private array $presentFields = [],
    ) {}

    public static function fromValidated(array $validated): self
    {
        return new self(
            name: $validated['name'] ?? null,
            price: $validated['price'] ?? null,
            category: isset($validated['category']) ? MenuCategory::from($validated['category']) : null,
            description: $validated['description'] ?? null,
            is_available: $validated['is_available'] ?? null,
            daily_stock: $validated['daily_stock'] ?? null,
            presentFields: array_keys($validated),
        );
    }

    public function toArray(): array
    {
        return array_filter(
            get_object_vars($this),
            fn (mixed $value, string $key) => $key !== 'presentFields' && in_array($key, $this->presentFields),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
