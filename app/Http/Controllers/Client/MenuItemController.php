<?php

namespace App\Http\Controllers\Client;

use App\Enums\MenuCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\MenuItemResource;
use App\Services\MenuItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    public function __construct(private MenuItemService $service) {}

    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category')
            ? MenuCategory::tryFrom($request->query('category'))
            : null;

        return MenuItemResource::collection($this->service->listForClient($category))->response();
    }
}
