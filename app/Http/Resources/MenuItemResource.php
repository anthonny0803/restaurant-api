<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'category' => $this->category->value,
            'is_available' => $this->is_available,
            'daily_stock' => $this->daily_stock,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
