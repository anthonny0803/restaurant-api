<?php

namespace App\Http\Requests;

use App\Enums\MenuCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:menu_items,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'category' => ['required', new Enum(MenuCategory::class)],
            'is_available' => ['sometimes', 'boolean'],
            'daily_stock' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
