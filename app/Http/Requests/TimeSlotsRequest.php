<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TimeSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'seats_requested' => ['required', 'integer', 'min:1'],
        ];
    }
}
