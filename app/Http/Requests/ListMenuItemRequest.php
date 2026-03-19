<?php

namespace App\Http\Requests;

use App\Enums\MenuCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'string', new Enum(MenuCategory::class)],
        ];
    }

    public function category(): ?MenuCategory
    {
        $category = $this->validated('category');

        return $category ? MenuCategory::from($category) : null;
    }
}
