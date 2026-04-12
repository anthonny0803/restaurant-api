<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantSetting extends Model
{
    protected $fillable = [
        'deposit_per_person',
        'cancellation_deadline_hours',
        'refund_percentage',
        'default_reservation_duration_minutes',
        'reminder_hours_before',
        'time_slot_interval_minutes',
        'opening_time',
        'closing_time',
    ];

    protected function casts(): array
    {
        return [
            'deposit_per_person' => 'decimal:2',
        ];
    }
}
