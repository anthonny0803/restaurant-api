<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\AnalyticsFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsFilterRequest;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $service) {}

    public function occupancy(AnalyticsFilterRequest $request): JsonResponse
    {
        $filter = new AnalyticsFilterDTO(...$request->validated());

        return response()->json($this->service->occupancy($filter));
    }

    public function revenue(AnalyticsFilterRequest $request): JsonResponse
    {
        $filter = new AnalyticsFilterDTO(...$request->validated());

        return response()->json($this->service->revenue($filter));
    }

    public function topMenuItems(AnalyticsFilterRequest $request): JsonResponse
    {
        $filter = new AnalyticsFilterDTO(...$request->validated());

        return response()->json($this->service->topMenuItems($filter));
    }
}
