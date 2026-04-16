<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function findOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    public function findWithProfile(int $id): User
    {
        return User::with('clientProfile')->findOrFail($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }
}
