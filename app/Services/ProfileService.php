<?php

namespace App\Services;

use App\DTOs\UpdatePasswordDTO;
use App\DTOs\UpdateProfileDTO;
use App\Models\User;

class ProfileService
{
    public function get(int $userId): User
    {
        return User::with('clientProfile')->findOrFail($userId);
    }

    public function update(UpdateProfileDTO $dto): User
    {
        $user = User::with('clientProfile')->findOrFail($dto->user_id);

        $userData = $dto->userData();
        if ($userData) {
            $user->update($userData);
        }

        if ($dto->hasPhone() && $user->clientProfile) {
            $user->clientProfile->update(['phone' => $dto->phone]);
        }

        return $user->fresh('clientProfile');
    }

    public function updatePassword(UpdatePasswordDTO $dto): User
    {
        $user = User::findOrFail($dto->user_id);

        $user->update(['password' => $dto->password]);

        return $user;
    }
}
