<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicRestaurantSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'opening_time'              => substr($this->opening_time, 0, 5),
            'closing_time'              => substr($this->closing_time, 0, 5),
            'time_slot_interval_minutes' => $this->time_slot_interval_minutes,
        ];
    }
}
