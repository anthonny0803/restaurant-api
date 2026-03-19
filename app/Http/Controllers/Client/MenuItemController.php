<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListMenuItemRequest;
use App\Http\Resources\MenuItemResource;
use App\Services\MenuItemService;
use Illuminate\Http\JsonResponse;

class MenuItemController extends Controller
{
    public function __construct(private MenuItemService $service) {}

    public function index(ListMenuItemRequest $request): JsonResponse
    {
        return MenuItemResource::collection($this->service->listForClient($request->category()))->response();
    }
}
