<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    public function __construct(private ReservationService $service) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Reservation::class);

        return ReservationResource::collection($this->service->listAll())->response();
    }

    public function show(Reservation $reservation): JsonResponse
    {
        $this->authorize('view', $reservation);

        return (new ReservationResource($reservation->load('table', 'payment')))->response();
    }
}
