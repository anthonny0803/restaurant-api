<?php

namespace App\DTOs;

readonly class AnalyticsFilterDTO
{
    public function __construct(
        public ?string $date_from = null,
        public ?string $date_to = null,
    ) {}
}
