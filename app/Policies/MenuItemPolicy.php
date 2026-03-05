<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, MenuItem $menuItem): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, MenuItem $menuItem): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, MenuItem $menuItem): bool
    {
        return $user->hasRole('admin');
    }
}
