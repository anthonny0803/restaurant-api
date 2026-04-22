<?php

namespace App\Services;

use App\DTOs\StorePreOrderDTO;
use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Repositories\MenuItemRepository;
use App\Repositories\ReservationItemRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreOrderService
{
    public function __construct(
        private ReservationItemRepository $reservationItemRepository,
        private MenuItemRepository $menuItemRepository,
    ) {}

    public function list(Reservation $reservation): Collection
    {
        return $this->reservationItemRepository->listForReservation($reservation);
    }

    public function store(Reservation $reservation, StorePreOrderDTO $dto): ReservationItem
    {
        $this->ensureReservationIsConfirmed($reservation);
        $this->ensureItemNotAlreadyOrdered($reservation, $dto->menu_item_id);

        return DB::transaction(function () use ($reservation, $dto) {
            $menuItem = $this->menuItemRepository->lockById($dto->menu_item_id);

            $this->ensureMenuItemExists($menuItem);
            $this->ensureMenuItemIsAvailable($menuItem);
            $this->ensureMenuItemHasStock($menuItem, $dto->quantity);

            $item = $this->reservationItemRepository->create([
                'reservation_id' => $reservation->id,
                'menu_item_id' => $menuItem->id,
                'quantity' => $dto->quantity,
                'unit_price' => $menuItem->price,
            ]);

            if ($menuItem->daily_stock !== null) {
                $this->menuItemRepository->decrementStock($menuItem, $dto->quantity);
            }

            $item->setRelation('menuItem', $menuItem);

            return $item;
        });
    }

    public function delete(ReservationItem $item): void
    {
        $this->ensureReservationIsConfirmed($item->reservation);

        DB::transaction(function () use ($item) {
            $menuItem = $item->menuItem;

            if ($menuItem->daily_stock !== null) {
                $this->menuItemRepository->incrementStock($menuItem, $item->quantity);
            }

            $this->reservationItemRepository->delete($item);
        });
    }

    private function ensureReservationIsConfirmed(Reservation $reservation): void
    {
        if ($reservation->status !== Reservation::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'reservation' => ['Solo puedes gestionar pre-orders en reservas confirmadas.'],
            ]);
        }
    }

    private function ensureMenuItemIsAvailable(MenuItem $menuItem): void
    {
        if (! $menuItem->is_available) {
            throw ValidationException::withMessages([
                'menu_item_id' => ['Este plato no esta disponible.'],
            ]);
        }
    }

    private function ensureMenuItemHasStock(MenuItem $menuItem, int $quantity): void
    {
        if ($menuItem->daily_stock !== null && $menuItem->daily_stock < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['No hay suficiente stock disponible para este plato.'],
            ]);
        }
    }

    private function ensureMenuItemExists(?MenuItem $menuItem): void
    {
        if ($menuItem === null) {
            throw ValidationException::withMessages([
                'menu_item_id' => ['El plato seleccionado no existe.'],
            ]);
        }
    }

    private function ensureItemNotAlreadyOrdered(Reservation $reservation, int $menuItemId): void
    {
        $exists = $reservation->reservationItems()
            ->where('menu_item_id', $menuItemId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'menu_item_id' => ['Este plato ya esta en tu pre-order. Eliminalo primero si deseas cambiar la cantidad.'],
            ]);
        }
    }
}
