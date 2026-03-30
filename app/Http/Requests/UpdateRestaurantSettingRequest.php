<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestaurantSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deposit_per_person'                 => ['sometimes', 'numeric', 'min:0.01'],
            'cancellation_deadline_hours'         => ['sometimes', 'integer', 'min:1', 'max:168'],
            'refund_percentage'                   => ['sometimes', 'integer', 'min:0', 'max:100'],
            'default_reservation_duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'reminder_hours_before'               => ['sometimes', 'integer', 'min:1', 'max:168'],
            'time_slot_interval_minutes'          => ['sometimes', 'integer', Rule::in([15, 30, 45, 60])],
        ];
    }
}
