<?php

namespace App\DTOs;

readonly class CompleteAccountDTO
{
    public function __construct(
        public int $user_id,
        public string $password,
    ) {}
}
