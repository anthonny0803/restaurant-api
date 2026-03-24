<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\UpdateRestaurantSettingDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRestaurantSettingRequest;
use App\Http\Resources\RestaurantSettingResource;
use App\Services\RestaurantSettingService;

class RestaurantSettingController extends Controller
{
    public function __construct(private RestaurantSettingService $service) {}

    public function show(): RestaurantSettingResource
    {
        return new RestaurantSettingResource($this->service->get());
    }

    public function update(UpdateRestaurantSettingRequest $request): RestaurantSettingResource
    {
        $settings = $this->service->update(
            UpdateRestaurantSettingDTO::fromValidated($request->validated())
        );

        return new RestaurantSettingResource($settings);
    }
}
