<?php

namespace App\Repositories;

use App\Models\RestaurantSetting;

class RestaurantSettingRepository
{
    public function get(): RestaurantSetting
    {
        return RestaurantSetting::firstOrFail();
    }

    public function update(RestaurantSetting $settings, array $data): RestaurantSetting
    {
        $settings->update($data);

        return $settings->fresh();
    }
}
