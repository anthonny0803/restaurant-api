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

                $openingMinutes = $this->toMinutes($opening);
                $closingMinutes = $this->toMinutes($closing);

                if ($openingMinutes >= $closingMinutes) {
                    $validator->errors()->add('opening_time', 'La hora de apertura debe ser anterior a la hora de cierre.');
                }

                if ($openingMinutes % $interval !== 0) {
                    $validator->errors()->add('opening_time', "La hora de apertura debe estar alineada a intervalos de {$interval} minutos.");
                }

                if ($closingMinutes % $interval !== 0) {
                    $validator->errors()->add('closing_time', "La hora de cierre debe estar alineada a intervalos de {$interval} minutos.");
                }
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
