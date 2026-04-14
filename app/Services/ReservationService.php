<?php

namespace App\Services;

use App\DTOs\AvailableTablesDTO;
use App\DTOs\HoldReservationDTO;
use App\Jobs\ExpireReservationJob;
use App\Notifications\ReservationCancelledNotification;
use App\Notifications\GuestReservationConfirmedNotification;
use App\Notifications\ReservationConfirmedNotification;
use App\Notifications\ReservationExpiredRefundNotification;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\RestaurantSetting;
use App\Repositories\ReservationRepository;
use App\Repositories\RestaurantSettingRepository;
use App\Repositories\TableRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    private const HOLD_DURATION_MINUTES = 10;

    public function __construct(
        private ReservationRepository $reservationRepository,
        private RestaurantSettingRepository $settingRepository,
        private TableRepository $tableRepository,
        private PaymentService $paymentService,
    ) {}

    public function hold(HoldReservationDTO $dto): array
    {
        $expiresAt = now()->addMinutes(self::HOLD_DURATION_MINUTES);

        $previousPayment = null;

        $result = DB::transaction(function () use ($dto, $expiresAt, &$previousPayment) {
            $pendingReservation = $this->reservationRepository->findPendingReservation($dto->user_id);

            if ($pendingReservation) {
                $previousPayment = $pendingReservation->payment;
                $this->releasePendingHold($pendingReservation);
            }

            $table = $this->tableRepository->findOrFail($dto->table_id);

            if (! $table->is_active) {
                throw ValidationException::withMessages([
                    'table_id' => ['Esta mesa no esta disponible.'],
                ]);
            }

            if ($dto->seats_requested < $table->min_capacity || $dto->seats_requested > $table->max_capacity) {
                throw ValidationException::withMessages([
                    'seats_requested' => ["Los comensales deben ser entre {$table->min_capacity} y {$table->max_capacity} para esta mesa."],
                ]);
            }

            $reservationDateTime = Carbon::parse($dto->date . ' ' . $dto->start_time);

            if ($reservationDateTime->isPast() || Carbon::parse($dto->date)->greaterThan(now()->addWeek())) {
                throw ValidationException::withMessages([
                    'date' => ['Las reservas deben ser dentro de los proximos 7 dias.'],
                ]);
            }

            $settings = $this->settingRepository->get();

            $endTime = Carbon::parse($dto->start_time)
                ->addMinutes($settings->default_reservation_duration_minutes)
                ->format('H:i:s');

            $this->validateBusinessHours($dto->start_time, $endTime, $settings);

            $startTimeMinutes = Carbon::parse($dto->start_time)->minute;
            if ($startTimeMinutes % $settings->time_slot_interval_minutes !== 0) {
                throw ValidationException::withMessages([
                    'start_time' => ["La hora de inicio debe estar alineada a intervalos de {$settings->time_slot_interval_minutes} minutos."],
                ]);
            }

            if ($this->reservationRepository->hasOverlappingReservation(
                $dto->table_id, $dto->date, $dto->start_time, $endTime
            )) {
                throw ValidationException::withMessages([
                    'table_id' => ['Esta mesa esta siendo gestionada por otro usuario o ya fue reservada para el horario seleccionado.'],
                ]);
            }

            $depositAmount = (float) $settings->deposit_per_person * $dto->seats_requested;

            $reservation = $this->reservationRepository->create([
                'user_id' => $dto->user_id,
                'table_id' => $dto->table_id,
                'seats_requested' => $dto->seats_requested,
                'date' => $dto->date,
                'start_time' => $dto->start_time,
                'end_time' => $endTime,
                'status' => Reservation::STATUS_PENDING,
                'expires_at' => $expiresAt,
            ]);

            $this->reservationRepository->createSnapshot($reservation, [
                'cancellation_deadline_hours' => $settings->cancellation_deadline_hours,
                'refund_percentage' => $settings->refund_percentage,
                'policy_accepted_at' => now(),
            ]);

            $paymentData = $this->paymentService->createPaymentIntent(
                $reservation->id,
                $depositAmount,
            );

            return [
                'reservation' => $reservation,
                'client_secret' => $paymentData['client_secret'],
            ];
        });

        if ($previousPayment) {
            $this->paymentService->cancelPaymentIntent($previousPayment);
        }

        ExpireReservationJob::dispatch($result['reservation']->id)
            ->delay($expiresAt);

        return $result;
    }

    public function confirmPayment(string $gatewayId): Reservation
    {
        $payment = $this->paymentService->handleSucceededPayment($gatewayId);
        $reservation = $payment->reservation;

        if ($reservation->status === Reservation::STATUS_CONFIRMED) {
            return $reservation;
        }

        if ($reservation->status === Reservation::STATUS_EXPIRED) {
            $this->paymentService->refund($payment, (float) $payment->amount);

            $reservation->user?->notify(
                new ReservationExpiredRefundNotification($reservation, (float) $payment->amount)
            );

            return $reservation;
        }

        $this->reservationRepository->updateStatus($reservation, Reservation::STATUS_CONFIRMED);

        $reservation = $reservation->fresh();

        if (! $reservation->user) {
            return $reservation;
        }

        if ($reservation->user->isGuest()) {
            $token = $reservation->user->createToken('guest-token')->plainTextToken;
            $reservation->user->notify(new GuestReservationConfirmedNotification($reservation, $token));

            return $reservation;
        }

        $reservation->user->notify(new ReservationConfirmedNotification($reservation));

        return $reservation;
    }

    public function cancel(Reservation $reservation): Reservation
    {
        if (! in_array($reservation->status, [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED])) {
            throw ValidationException::withMessages([
                'reservation' => ['Esta reserva no puede ser cancelada.'],
            ]);
        }

        $reservationDateTime = Carbon::parse($reservation->date->format('Y-m-d') . ' ' . $reservation->start_time);

        $refundAmount = DB::transaction(function () use ($reservation, $reservationDateTime) {
            $this->reservationRepository->updateStatus($reservation, Reservation::STATUS_CANCELLED);

            $payment = $reservation->payment;

            if (! $payment) {
                return null;
            }

            if ($payment->status === Payment::STATUS_PENDING) {
                $this->paymentService->cancelPaymentIntent($payment);

                return null;
            }

            if ($payment->status !== Payment::STATUS_SUCCEEDED) {
                return null;
            }

            $snapshot = $reservation->cancellationPolicySnapshot;
            $hoursUntilReservation = now()->diffInHours($reservationDateTime, false);

            $refundAmount = $hoursUntilReservation >= $snapshot->cancellation_deadline_hours
                ? (float) $payment->amount
                : (float) $payment->amount * $snapshot->refund_percentage / 100;

            $this->paymentService->refund($payment, $refundAmount);

            return $refundAmount;
        });

        $reservation = $reservation->fresh();

        if ($reservation->user) {
            $reservation->user->notify(new ReservationCancelledNotification($reservation, $refundAmount));
        }

        return $reservation;
    }

    public function markAsNoShow(Reservation $reservation): Reservation
    {
        if ($reservation->status !== Reservation::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'reservation' => ['Solo las reservas completadas pueden marcarse como no show.'],
            ]);
        }

        $this->reservationRepository->updateStatus($reservation, Reservation::STATUS_NO_SHOW);

        return $reservation->fresh();
    }

    public function find(int $id): ?Reservation
    {
        return $this->reservationRepository->find($id);
    }

    public function listForUser(int $userId, int $perPage = 6): LengthAwarePaginator
    {
        return $this->reservationRepository->paginateForUser($userId, $perPage);
    }

    public function listAll(int $perPage = 6): LengthAwarePaginator
    {
        return $this->reservationRepository->paginate($perPage);
    }

    public function suggestAvailableTables(AvailableTablesDTO $dto): Collection
    {
        $reservationDateTime = Carbon::parse($dto->date . ' ' . $dto->start_time);

        if ($reservationDateTime->isPast() || Carbon::parse($dto->date)->greaterThan(now()->addWeek())) {
            throw ValidationException::withMessages([
                'date' => ['Las reservas deben ser dentro de los proximos 7 dias.'],
            ]);
        }

        $settings = $this->settingRepository->get();

        $endTime = Carbon::parse($dto->start_time)
            ->addMinutes($settings->default_reservation_duration_minutes)
            ->format('H:i:s');

        $this->validateBusinessHours($dto->start_time, $endTime, $settings);

        $startTimeMinutes = Carbon::parse($dto->start_time)->minute;
        if ($startTimeMinutes % $settings->time_slot_interval_minutes !== 0) {
            throw ValidationException::withMessages([
                'start_time' => ["La hora de inicio debe estar alineada a intervalos de {$settings->time_slot_interval_minutes} minutos."],
            ]);
        }

        return $this->tableRepository->findAvailable($dto->seats_requested, $dto->date, $dto->start_time, $endTime);
    }

    private function releasePendingHold(Reservation $pendingReservation): void
    {
        $status = $pendingReservation->expires_at->isPast()
            ? Reservation::STATUS_EXPIRED
            : Reservation::STATUS_CANCELLED;

        $this->reservationRepository->updateStatus($pendingReservation, $status);
    }

    private function validateBusinessHours(string $startTime, string $endTime, RestaurantSetting $settings): void
    {
        $opening = substr($settings->opening_time, 0, 5);
        $closing = substr($settings->closing_time, 0, 5);

        if (substr($startTime, 0, 5) < $opening || substr($endTime, 0, 5) > $closing) {
            throw ValidationException::withMessages([
                'start_time' => ["La reserva debe estar dentro del horario de apertura: {$opening} - {$closing}."],
            ]);
        }
    }
}
