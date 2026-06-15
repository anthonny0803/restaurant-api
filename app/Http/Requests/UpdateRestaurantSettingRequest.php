<?php

namespace App\Http\Requests;

use App\Rules\AlignedToInterval;
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
            'default_reservation_duration_minutes' => ['sometimes', 'integer', Rule::in([30, 60, 90])],
            'reminder_hours_before'               => ['sometimes', 'integer', 'min:1', 'max:168'],
            'time_slot_interval_minutes'          => ['sometimes', 'integer', Rule::in([30, 60])],
            'opening_time'                        => ['sometimes', 'date_format:H:i'],
            'closing_time'                        => ['sometimes', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $settings = $this->existingSettings();
                $opening = $this->input('opening_time', $settings->opening_time);
                $closing = $this->input('closing_time', $settings->closing_time);
                $interval = (int) $this->input('time_slot_interval_minutes', $settings->time_slot_interval_minutes);

                if ($this->toMinutes($opening) >= $this->toMinutes($closing)) {
                    $validator->errors()->add('opening_time', 'La hora de apertura debe ser anterior a la hora de cierre.');
                }

                $alignedToInterval = new AlignedToInterval($interval);
                $alignedToInterval->validate('opening_time', $opening, fn ($message) => $validator->errors()->add('opening_time', $message));
                $alignedToInterval->validate('closing_time', $closing, fn ($message) => $validator->errors()->add('closing_time', $message));
            },
        ];
    }

    private function toMinutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }

    private function existingSettings(): \App\Models\RestaurantSetting
    {
        return \App\Models\RestaurantSetting::firstOrFail();
    }
}
