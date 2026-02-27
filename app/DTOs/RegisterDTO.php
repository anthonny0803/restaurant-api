<?php

namespace App\DTOs;

readonly class RegisterDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
