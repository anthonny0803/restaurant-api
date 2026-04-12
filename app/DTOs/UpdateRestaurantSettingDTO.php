<?php

namespace App\DTOs;

readonly class UpdateRestaurantSettingDTO
{
    public function __construct(
        public ?float $deposit_per_person = null,
        public ?int $cancellation_deadline_hours = null,
        public ?int $refund_percentage = null,
        public ?int $default_reservation_duration_minutes = null,
        public ?int $reminder_hours_before = null,
        public ?int $time_slot_interval_minutes = null,
        public ?string $opening_time = null,
        public ?string $closing_time = null,
        private array $presentFields = [],
    ) {}

    public static function fromValidated(array $validated): self
    {
        return new self(
            deposit_per_person: $validated['deposit_per_person'] ?? null,
            cancellation_deadline_hours: $validated['cancellation_deadline_hours'] ?? null,
            refund_percentage: $validated['refund_percentage'] ?? null,
            default_reservation_duration_minutes: $validated['default_reservation_duration_minutes'] ?? null,
            reminder_hours_before: $validated['reminder_hours_before'] ?? null,
            time_slot_interval_minutes: $validated['time_slot_interval_minutes'] ?? null,
            opening_time: $validated['opening_time'] ?? null,
            closing_time: $validated['closing_time'] ?? null,
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
