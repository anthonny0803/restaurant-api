<?php

namespace App\Http\Controllers;

use App\DTOs\UpdatePasswordDTO;
use App\DTOs\UpdateProfileDTO;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private ProfileService $service) {}

    public function show(Request $request): UserResource
    {
        return new UserResource($this->service->get($request->user()->id));
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $this->service->update(
            UpdateProfileDTO::fromValidated($request->user()->id, $request->validated())
        );

        return new UserResource($user);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->service->updatePassword(new UpdatePasswordDTO(
            user_id: $request->user()->id,
            password: $request->validated('password'),
        ));

        return response()->json([
            'message' => 'Contrasena actualizada correctamente.',
        ]);
    }
}
