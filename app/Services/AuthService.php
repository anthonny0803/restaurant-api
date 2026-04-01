<?php

namespace App\Services;

use App\DTOs\CompleteAccountDTO;
use App\DTOs\ForgotPasswordDTO;
use App\DTOs\LoginDTO;
use App\DTOs\RegisterDTO;
use App\DTOs\ResetPasswordDTO;
use App\Models\User;
use App\Notifications\AccountCompletionNotification;
use App\Notifications\ResetPasswordNotification;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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

        $user->tokens()->delete();

        return $user;
    }

    public function forgotPassword(ForgotPasswordDTO $dto): void
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if (! $user) {
            return;
        }

        $broker = Password::broker();

        if ($broker->getRepository()->recentlyCreatedToken($user)) {
            return;
        }

        $token = $broker->createToken($user);

        $notification = $user->isGuest()
            ? new AccountCompletionNotification($token)
            : new ResetPasswordNotification($token);

        $user->notify($notification);
    }

    public function resetPassword(ResetPasswordDTO $dto): void
    {
        $status = Password::reset(
            [
                'email'                 => $dto->email,
                'token'                 => $dto->token,
                'password'              => $dto->password,
                'password_confirmation' => $dto->password_confirmation,
            ],
            function (User $user, string $password) {
                $this->userRepository->update($user, ['password' => $password]);
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['El token de restablecimiento es invalido o ha expirado.'],
            ]);
        }
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
