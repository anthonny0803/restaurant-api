<?php

namespace App\Services;

use App\DTOs\UpdatePasswordDTO;
use App\DTOs\UpdateProfileDTO;
use App\Models\User;
use App\Repositories\UserRepository;

class ProfileService
{
    public function __construct(private UserRepository $repository) {}

    public function get(int $userId): User
    {
        return $this->repository->findWithProfile($userId);
    }

    public function update(UpdateProfileDTO $dto): User
    {
        $user = $this->repository->findWithProfile($dto->user_id);

        $userData = $dto->userData();
        if ($userData) {
            $this->repository->update($user, $userData);
        }

        if ($dto->hasPhone() && $user->clientProfile) {
            $user->clientProfile->update(['phone' => $dto->phone]);
        }

        return $user->fresh('clientProfile');
    }

    public function updatePassword(UpdatePasswordDTO $dto): User
    {
        $user = $this->repository->findOrFail($dto->user_id);

        $this->repository->update($user, ['password' => $dto->password]);

        return $user;
    }
}
