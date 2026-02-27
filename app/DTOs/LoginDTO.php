<?php

namespace App\DTOs;

readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
