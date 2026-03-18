<?php

namespace App\Http\Controllers\Client;

use App\DTOs\StorePreOrderDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePreOrderRequest;
use App\Http\Resources\ReservationItemResource;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Services\PreOrderService;
use Illuminate\Http\JsonResponse;

class PreOrderController extends Controller
{
    public function __construct(private PreOrderService $service) {}

    public function index(Reservation $reservation): JsonResponse
    {
        $this->authorize('managePreOrders', $reservation);

        $items = $this->service->list($reservation);

        return ReservationItemResource::collection($items)->response();
    }

    public function store(StorePreOrderRequest $request, Reservation $reservation): JsonResponse
    {
        $this->authorize('managePreOrders', $reservation);

        $item = $this->service->store($reservation, new StorePreOrderDTO(
            menu_item_id: $request->validated('menu_item_id'),
            quantity: $request->validated('quantity'),
        ));

        return (new ReservationItemResource($item))->response()->setStatusCode(201);
    }

    public function destroy(Reservation $reservation, ReservationItem $reservationItem): JsonResponse
    {
        $this->authorize('managePreOrders', $reservation);

        $this->service->delete($reservationItem);

        return response()->json(null, 204);
    }
}
