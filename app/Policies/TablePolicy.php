<?php

namespace App\Policies;

use App\Models\Table;
use App\Models\User;

class TablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Table $table): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Table $table): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Table $table): bool
    {
        return $user->hasRole('admin');
    }
}
