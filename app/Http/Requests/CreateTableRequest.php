<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:100', 'unique:tables,name'],
            'min_capacity' => ['required', 'integer', 'min:1'],
            'max_capacity' => ['required', 'integer', 'gte:min_capacity'],
            'location'     => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }
}
