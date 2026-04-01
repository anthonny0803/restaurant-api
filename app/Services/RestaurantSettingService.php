<?php

namespace App\Services;

use App\DTOs\UpdateRestaurantSettingDTO;
use App\Models\RestaurantSetting;
use App\Repositories\RestaurantSettingRepository;

class RestaurantSettingService
{
    public function __construct(private RestaurantSettingRepository $repository) {}

    public function get(): RestaurantSetting
    {
        return $this->repository->get();
    }

    public function update(UpdateRestaurantSettingDTO $dto): RestaurantSetting
    {
        $settings = $this->repository->get();

        return $this->repository->update($settings, $dto->toArray());
    }
}
