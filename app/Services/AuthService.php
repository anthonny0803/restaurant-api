<?php

namespace App\Services;

use App\DTOs\CompleteAccountDTO;
use App\DTOs\LoginDTO;
use App\DTOs\RegisterDTO;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function register(RegisterDTO $dto): array
    {
        $user = DB::transaction(function () use ($dto) {
            $user = $this->userRepository->create([
                'name'     => $dto->name,
                'email'    => $dto->email,
                'password' => $dto->password,
            ]);

            $user->assignRole('client');
            $user->clientProfile()->create([]);

            return $user;
        });

        $token = $user->createToken('api-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function login(LoginDTO $dto): array
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function completeAccount(CompleteAccountDTO $dto): User
    {
        $user = $this->userRepository->findOrFail($dto->user_id);

        if (! $user->isGuest()) {
            throw ValidationException::withMessages([
                'account' => ['Esta cuenta ya tiene una contrasena establecida.'],
            ]);
        }

        $this->userRepository->update($user, ['password' => $dto->password]);

        return $user;
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
