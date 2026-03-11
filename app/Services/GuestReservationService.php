<?php

namespace App\Services;

use App\DTOs\GuestHoldReservationDTO;
use App\DTOs\HoldReservationDTO;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuestReservationService
{
    public function __construct(
        private UserRepository $userRepository,
        private ReservationService $reservationService,
    ) {}

    public function hold(GuestHoldReservationDTO $dto): array
    {
        $user = $this->resolveUser($dto);

        $holdDTO = new HoldReservationDTO(
            user_id: $user->id,
            table_id: $dto->table_id,
            seats_requested: $dto->seats_requested,
            date: $dto->date,
            start_time: $dto->start_time,
        );

        return $this->reservationService->hold($holdDTO);
    }

    private function resolveUser(GuestHoldReservationDTO $dto): User
    {
        $existingUser = $this->userRepository->findByEmail($dto->email);

        if ($existingUser && ! $existingUser->isGuest()) {
            throw ValidationException::withMessages([
                'email' => ['Este correo ya tiene una cuenta registrada. Por favor, inicia sesion para hacer tu reserva.'],
            ]);
        }

        if ($existingUser) {
            return $existingUser;
        }

        return DB::transaction(function () use ($dto) {
            $user = $this->userRepository->create([
                'name' => $dto->name,
                'email' => $dto->email,
            ]);

            $user->assignRole('client');
            $user->clientProfile()->create(['phone' => $dto->phone]);

            return $user;
        });
    }
}
