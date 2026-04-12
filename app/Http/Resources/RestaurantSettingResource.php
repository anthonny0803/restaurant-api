<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'deposit_per_person'                    => $this->deposit_per_person,
            'cancellation_deadline_hours'           => $this->cancellation_deadline_hours,
            'refund_percentage'                     => $this->refund_percentage,
            'default_reservation_duration_minutes'  => $this->default_reservation_duration_minutes,
            'reminder_hours_before'                 => $this->reminder_hours_before,
            'time_slot_interval_minutes'            => $this->time_slot_interval_minutes,
            'opening_time'                          => substr($this->opening_time, 0, 5),
            'closing_time'                          => substr($this->closing_time, 0, 5),
        ];
    }
}
