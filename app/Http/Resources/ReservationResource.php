<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'table' => new TableResource($this->whenLoaded('table')),
            'seats_requested' => $this->seats_requested,
            'date' => $this->date->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'expires_at' => $this->when(
                $this->status === Reservation::STATUS_PENDING,
                $this->expires_at?->toDateTimeString(),
            ),
            'payment' => $this->when(
                $this->relationLoaded('payment') && $this->payment,
                function () {
                    return [
                        'amount' => $this->payment->amount,
                        'status' => $this->payment->status,
                        'paid_at' => $this->payment->paid_at?->toDateTimeString(),
                    ];
                },
            ),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
