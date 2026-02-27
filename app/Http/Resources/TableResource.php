<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'min_capacity' => $this->min_capacity,
            'max_capacity' => $this->max_capacity,
            'location'     => $this->location,
            'description'  => $this->description,
            'is_active'    => $this->is_active,
            'created_at'   => $this->created_at->toDateTimeString(),
        ];
    }
}
