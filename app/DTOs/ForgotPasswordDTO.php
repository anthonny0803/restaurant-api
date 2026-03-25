<?php

namespace App\DTOs;

readonly class ForgotPasswordDTO
{
    public function __construct(
        public string $email,
    ) {}
}
