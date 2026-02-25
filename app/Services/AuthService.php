<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function register(array $data): array
    {
        $user = $this->userRepository->create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
        ]);

        $user->assignRole('client');
        $user->clientProfile()->create([]);

        $token = $user->createToken('api-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function login(array $data): array
    {
        $user = $this->userRepository->findByEmail($data['email']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
