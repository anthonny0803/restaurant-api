<?php

namespace App\Repositories;

use App\Models\ClientProfile;
use App\Models\User;

class ClientProfileRepository
{
    public function create(User $user, array $data): ClientProfile
    {
        return $user->clientProfile()->create($data);
    }

    public function update(ClientProfile $profile, array $data): ClientProfile
    {
        $profile->update($data);

        return $profile->fresh();
    }
}
