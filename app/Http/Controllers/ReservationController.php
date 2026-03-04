<?php

namespace App\Http\Controllers;

use App\DTOs\HoldReservationDTO;
use App\Http\Requests\HoldReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(private ReservationService $service) {}

    public function store(HoldReservationRequest $request): JsonResponse
    {
        $this->authorize('create', Reservation::class);

        $dto = new HoldReservationDTO(
            user_id: $request->user()->id,
            table_id: $request->validated('table_id'),
            seats_requested: $request->validated('seats_requested'),
            date: $request->validated('date'),
            start_time: $request->validated('start_time'),
        );

        $result = $this->service->hold($dto);

        return response()->json([
            'reservation' => new ReservationResource($result['reservation']->load('table', 'payment')),
            'client_secret' => $result['client_secret'],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Reservation::class);

        $user = $request->user();

        $reservations = $user->hasRole('admin')
            ? $this->service->listAll()
            : $this->service->listForUser($user->id);

        return ReservationResource::collection($reservations)->response();
    }

    public function show(Reservation $reservation): JsonResponse
    {
        $this->authorize('view', $reservation);

        return (new ReservationResource($reservation->load('table', 'payment')))->response();
    }

    public function cancel(Reservation $reservation): JsonResponse
    {
        $this->authorize('cancel', $reservation);

        $reservation = $this->service->cancel($reservation);

        return (new ReservationResource($reservation->load('table', 'payment')))->response();
    }
}
