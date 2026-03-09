<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => number_format($this->quantity * $this->unit_price, 2, '.', ''),
            'menu_item' => [
                'id' => $this->menuItem->id,
                'name' => $this->menuItem->name,
                'category' => $this->menuItem->category->value,
            ],
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
