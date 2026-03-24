<?php

namespace App\Http\Controllers\Client;

use App\DTOs\AvailableTablesDTO;
use App\DTOs\HoldReservationDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvailableTablesRequest;
use App\Http\Requests\HoldReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Http\Resources\TableResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(private ReservationService $service) {}

    public function availableTables(AvailableTablesRequest $request): JsonResponse
    {
        $dto = new AvailableTablesDTO(...$request->validated());

        $tables = $this->service->suggestAvailableTables($dto);

        return TableResource::collection($tables)->response();
    }

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

        return ReservationResource::collection(
            $this->service->listForUser($request->user()->id)
        )->response();
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
