<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

class AlignedToInterval implements ValidationRule
{
    public function __construct(private readonly int $interval)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $time = Carbon::parse($value);
        $totalMinutes = $time->hour * 60 + $time->minute;

        if ($totalMinutes % $this->interval !== 0) {
            $fail("Debe estar alineada a intervalos de {$this->interval} minutos.");
        }
    }
}
