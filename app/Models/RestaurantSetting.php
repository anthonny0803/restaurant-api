<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantSetting extends Model
{
    protected $fillable = [
        'deposit_per_person',
        'cancellation_deadline_hours',
        'refund_percentage',
        'admin_fee_percentage',
        'default_reservation_duration_minutes',
        'reminder_hours_before',
    ];

    protected function casts(): array
    {
        return [
            'deposit_per_person' => 'decimal:2',
        ];
    }
}
