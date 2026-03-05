<?php

namespace App\Http\Controllers;

use App\DTOs\StoreMenuItemDTO;
use App\DTOs\UpdateMenuItemDTO;
use App\Enums\MenuCategory;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use App\Services\MenuItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    public function __construct(private MenuItemService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MenuItem::class);

        $category = $request->query('category')
            ? MenuCategory::tryFrom($request->query('category'))
            : null;

        return MenuItemResource::collection($this->service->paginate($category))->response();
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $this->authorize('create', MenuItem::class);

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
        $this->authorize('view', $menuItem);

        return new MenuItemResource($menuItem);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): MenuItemResource
    {
        $this->authorize('update', $menuItem);

        $menuItem = $this->service->update($menuItem, UpdateMenuItemDTO::fromValidated($request->validated()));

        return new MenuItemResource($menuItem);
    }

    public function destroy(MenuItem $menuItem): JsonResponse
    {
        $this->authorize('delete', $menuItem);

        $this->service->delete($menuItem);

        return response()->json(null, 204);
    }
}
