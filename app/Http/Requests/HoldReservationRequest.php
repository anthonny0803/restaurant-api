<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HoldReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'seats_requested' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
        ];
    }
}
