<?php

namespace Tests\Unit\Rules;

use App\Rules\AlignedToInterval;
use PHPUnit\Framework\TestCase;

class AlignedToIntervalTest extends TestCase
{
    private function fails(int $interval, string $value): bool
    {
        $failed = false;
        $rule = new AlignedToInterval($interval);
        $rule->validate('time', $value, function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    }

    public function test_passes_when_time_is_aligned_to_30_minute_interval(): void
    {
        $this->assertFalse($this->fails(30, '09:00'));
        $this->assertFalse($this->fails(30, '09:30'));
        $this->assertFalse($this->fails(30, '13:30:00'));
    }

    public function test_fails_when_time_is_not_aligned_to_30_minute_interval(): void
    {
        $this->assertTrue($this->fails(30, '09:15'));
        $this->assertTrue($this->fails(30, '09:45'));
    }

    public function test_passes_when_time_is_aligned_to_60_minute_interval(): void
    {
        $this->assertFalse($this->fails(60, '09:00'));
        $this->assertFalse($this->fails(60, '22:00:00'));
    }

    public function test_fails_when_time_is_not_aligned_to_60_minute_interval(): void
    {
        $this->assertTrue($this->fails(60, '09:30'));
        $this->assertTrue($this->fails(60, '09:15'));
    }

    public function test_abstains_when_value_is_empty(): void
    {
        $this->assertFalse($this->fails(30, ''));
    }
}
