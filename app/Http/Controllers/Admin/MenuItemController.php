<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\StoreMenuItemDTO;
use App\DTOs\UpdateMenuItemDTO;
use App\Enums\MenuCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListMenuItemRequest;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use App\Services\MenuItemService;
use Illuminate\Http\JsonResponse;

class MenuItemController extends Controller
{
    public function __construct(private MenuItemService $service) {}

    public function index(ListMenuItemRequest $request): JsonResponse
    {
        return MenuItemResource::collection($this->service->paginate($request->category()))->response();
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $menuItem = $this->service->create(new StoreMenuItemDTO(
            name: $request->validated('name'),
            price: $request->validated('price'),
            category: MenuCategory::from($request->validated('category')),
            description: $request->validated('description'),
            is_available: $request->validated('is_available', true),
            daily_stock: $request->validated('daily_stock'),
        ));

        return (new MenuItemResource($menuItem))->response()->setStatusCode(201);
    }

    public function show(MenuItem $menuItem): MenuItemResource
    {
        return new MenuItemResource($menuItem);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): MenuItemResource
    {
        $menuItem = $this->service->update($menuItem, UpdateMenuItemDTO::fromValidated($request->validated()));

        return new MenuItemResource($menuItem);
    }

    public function destroy(MenuItem $menuItem): JsonResponse
    {
        $this->service->delete($menuItem);

        return response()->json(null, 204);
    }
}
