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
        private array $presentFields = [],
    ) {}

    public static function fromValidated(array $validated): self
    {
        return new self(
            name: $validated['name'] ?? null,
            min_capacity: $validated['min_capacity'] ?? null,
            max_capacity: $validated['max_capacity'] ?? null,
            location: $validated['location'] ?? null,
            description: $validated['description'] ?? null,
            is_active: $validated['is_active'] ?? null,
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
