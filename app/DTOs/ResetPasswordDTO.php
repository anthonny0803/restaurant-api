<?php

namespace App\DTOs;

readonly class ResetPasswordDTO
{
    public function __construct(
        public string $email,
        public string $token,
        public string $password,
        public string $password_confirmation,
    ) {}
}
