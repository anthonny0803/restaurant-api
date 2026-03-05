<?php

namespace App\Http\Requests;

use App\Enums\MenuCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('menu_items', 'name')->ignore($this->route('menu_item'))],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'price' => ['sometimes', 'numeric', 'min:0.01'],
            'category' => ['sometimes', new Enum(MenuCategory::class)],
            'is_available' => ['sometimes', 'boolean'],
            'daily_stock' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
