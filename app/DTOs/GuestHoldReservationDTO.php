<?php

namespace App\DTOs;

readonly class GuestHoldReservationDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public int $table_id,
        public int $seats_requested,
        public string $date,
        public string $start_time,
    ) {}
}
