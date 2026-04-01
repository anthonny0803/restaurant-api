<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'anthonny0803@gmail.com'],
            [
                'name' => 'admin',
                'password' => 'Admin000',
            ]
        );

        $admin->assignRole('admin');
    }
}
