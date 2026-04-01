<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\CompleteAccountDTO;
use App\DTOs\ForgotPasswordDTO;
use App\DTOs\LoginDTO;
use App\DTOs\RegisterDTO;
use App\DTOs\ResetPasswordDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteAccountRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register(new RegisterDTO(...$request->validated()));

        return response()->json([
            'data' => [
                'user'  => new UserResource($result['user']),
                'token' => $result['token'],
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(new LoginDTO(...$request->validated()));

        return response()->json([
            'data' => [
                'user'  => new UserResource($result['user']),
                'token' => $result['token'],
            ],
        ]);
    }

    public function completeAccount(CompleteAccountRequest $request): JsonResponse
    {
        $dto = new CompleteAccountDTO(
            user_id: $request->user()->id,
            password: $request->validated('password'),
        );

        $user = $this->authService->completeAccount($dto);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword(new ForgotPasswordDTO(...$request->validated()));

        return response()->json([
            'message' => 'Si tu correo esta registrado, recibiras instrucciones para restablecer tu contrasena.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword(new ResetPasswordDTO(...$request->validated()));

        return response()->json([
            'message' => 'Tu contrasena ha sido restablecida exitosamente.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}
