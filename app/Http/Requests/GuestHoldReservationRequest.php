<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestHoldReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{8}$/'],
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'seats_requested' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
        ];
    }
}
