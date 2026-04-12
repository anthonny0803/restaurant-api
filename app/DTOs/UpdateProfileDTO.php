<?php

namespace App\DTOs;

readonly class UpdateProfileDTO
{
    public function __construct(
        public int $user_id,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        private array $presentFields = [],
    ) {}

    public static function fromValidated(int $userId, array $validated): self
    {
        return new self(
            user_id: $userId,
            name: $validated['name'] ?? null,
            email: $validated['email'] ?? null,
            phone: $validated['phone'] ?? null,
            presentFields: array_keys($validated),
        );
    }

    public function userData(): array
    {
        return array_filter(
            ['name' => $this->name, 'email' => $this->email],
            fn (mixed $value, string $key) => in_array($key, $this->presentFields),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function hasPhone(): bool
    {
        return in_array('phone', $this->presentFields);
    }
}
