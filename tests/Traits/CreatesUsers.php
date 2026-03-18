<?php

namespace Tests\Traits;

use App\Models\User;

trait CreatesUsers
{
    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }
}
