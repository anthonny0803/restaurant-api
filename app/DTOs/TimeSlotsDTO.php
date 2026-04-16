<?php

namespace App\DTOs;

readonly class TimeSlotsDTO
{
    public function __construct(
        public string $date,
        public int $seats_requested,
    ) {}
}
