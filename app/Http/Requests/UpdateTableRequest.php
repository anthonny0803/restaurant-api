<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $table = $this->route('table');

        $maxCapacityFloor = $this->has('min_capacity') ? 'gte:min_capacity' : "min:{$table->min_capacity}";
        $minCapacityCeiling = $this->has('max_capacity') ? 'lte:max_capacity' : "max:{$table->max_capacity}";

        return [
            'name'         => ['sometimes', 'string', 'max:100', Rule::unique('tables', 'name')->ignore($table)],
            'min_capacity' => ['sometimes', 'integer', 'min:1', $minCapacityCeiling],
            'max_capacity' => ['sometimes', 'integer', $maxCapacityFloor],
            'location'     => ['sometimes', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }
}
