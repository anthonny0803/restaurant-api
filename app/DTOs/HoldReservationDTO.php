<?php

namespace App\DTOs;

readonly class HoldReservationDTO
{
    public function __construct(
        public int $user_id,
        public int $table_id,
        public int $seats_requested,
        public string $date,
        public string $start_time,
    ) {}
}
