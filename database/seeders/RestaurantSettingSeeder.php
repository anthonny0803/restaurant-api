<?php

namespace Database\Seeders;

use App\Models\RestaurantSetting;
use Illuminate\Database\Seeder;

class RestaurantSettingSeeder extends Seeder
{
    public function run(): void
    {
        RestaurantSetting::firstOrCreate([], [
            'deposit_per_person' => 5.00,
            'cancellation_deadline_hours' => 24,
            'refund_percentage' => 50,
            'default_reservation_duration_minutes' => 60,
            'reminder_hours_before' => 24,
            'time_slot_interval_minutes' => 30,
            'opening_time' => '09:00',
            'closing_time' => '23:00',
        ]);
    }
}
