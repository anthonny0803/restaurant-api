<?php

namespace App\Http\Controllers;

use App\DTOs\CompleteAccountDTO;
use App\DTOs\LoginDTO;
use App\DTOs\RegisterDTO;
use App\Http\Requests\CompleteAccountRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
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

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}
