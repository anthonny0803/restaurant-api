<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('table'));
    }

    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'string', 'max:100', Rule::unique('tables', 'name')->ignore($this->route('table'))],
            'min_capacity' => ['sometimes', 'integer', 'min:1'],
            'max_capacity' => ['sometimes', 'integer', 'gte:min_capacity'],
            'location'     => ['sometimes', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }
}
