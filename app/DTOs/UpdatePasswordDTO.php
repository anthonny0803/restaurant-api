<?php

namespace App\DTOs;

readonly class UpdatePasswordDTO
{
    public function __construct(
        public int $user_id,
        public string $password,
    ) {}
}
