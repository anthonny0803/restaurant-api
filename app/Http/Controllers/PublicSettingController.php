<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicRestaurantSettingResource;
use App\Services\RestaurantSettingService;

class PublicSettingController extends Controller
{
    public function __invoke(RestaurantSettingService $service): PublicRestaurantSettingResource
    {
        return new PublicRestaurantSettingResource($service->get());
    }
}
