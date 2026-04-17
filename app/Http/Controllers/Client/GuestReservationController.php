<?php

namespace App\Http\Controllers\Client;

use App\DTOs\GuestHoldReservationDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuestHoldReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Services\GuestReservationService;
use Illuminate\Http\JsonResponse;

class GuestReservationController extends Controller
{
    public function __construct(
        private GuestReservationService $guestReservationService,
    ) {}

    public function store(GuestHoldReservationRequest $request): JsonResponse
    {
        $dto = new GuestHoldReservationDTO(...$request->validated());

        $result = $this->guestReservationService->hold($dto);

        return response()->json([
            'data' => [
                'reservation' => new ReservationResource($result['reservation']->load('table', 'payment')),
                'client_secret' => $result['client_secret'],
            ],
        ], 201);
    }
}
