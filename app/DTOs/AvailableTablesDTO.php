<?php

namespace App\DTOs;

readonly class AvailableTablesDTO
{
    public function __construct(
        public int $seats_requested,
        public string $date,
        public string $start_time,
    ) {}
}
