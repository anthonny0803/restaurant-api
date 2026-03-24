<?php

namespace App\Services;

use App\DTOs\HoldReservationDTO;
use App\Jobs\ExpireReservationJob;
use App\Notifications\ReservationCancelledNotification;
use App\Notifications\GuestReservationConfirmedNotification;
use App\Notifications\ReservationConfirmedNotification;
use App\Notifications\ReservationExpiredRefundNotification;
use App\Models\Payment;
use App\Models\Reservation;
use App\Repositories\ReservationRepository;
use App\Repositories\RestaurantSettingRepository;
use App\Repositories\TableRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    private const HOLD_DURATION_MINUTES = 15;

    public function __construct(
        private ReservationRepository $reservationRepository,
        private RestaurantSettingRepository $settingRepository,
        private TableRepository $tableRepository,
        private PaymentService $paymentService,
    ) {}

    public function hold(HoldReservationDTO $dto): array
    {
        $expiresAt = now()->addMinutes(self::HOLD_DURATION_MINUTES);

        $result = DB::transaction(function () use ($dto, $expiresAt) {
            if ($this->reservationRepository->hasPendingReservation($dto->user_id)) {
                throw ValidationException::withMessages([
                    'reservation' => ['Ya tienes una reserva pendiente de pago.'],
                ]);
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

            $startTimeMinutes = Carbon::parse($dto->start_time)->minute;
            if ($startTimeMinutes % $settings->time_slot_interval_minutes !== 0) {
                throw ValidationException::withMessages([
                    'start_time' => ["La hora de inicio debe estar alineada a intervalos de {$settings->time_slot_interval_minutes} minutos."],
                ]);
            }

            $endTime = Carbon::parse($dto->start_time)
                ->addMinutes($settings->default_reservation_duration_minutes)
                ->format('H:i:s');

            if ($this->reservationRepository->hasOverlappingReservation(
                $dto->table_id, $dto->date, $dto->start_time, $endTime
            )) {
                throw ValidationException::withMessages([
                    'table_id' => ['Esta mesa no esta disponible para el horario seleccionado.'],
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
                'admin_fee_percentage' => $settings->admin_fee_percentage,
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

            if ($payment && $payment->status === Payment::STATUS_SUCCEEDED) {
                $snapshot = $reservation->cancellationPolicySnapshot;
                $hoursUntilReservation = now()->diffInHours($reservationDateTime, false);

                $refundAmount = $hoursUntilReservation >= $snapshot->cancellation_deadline_hours
                    ? (float) $payment->amount
                    : (float) $payment->amount * $snapshot->refund_percentage / 100;

                $this->paymentService->refund($payment, $refundAmount);

                return $refundAmount;
            }

            return null;
        });

        $reservation = $reservation->fresh();

        if ($reservation->user) {
            $reservation->user->notify(new ReservationCancelledNotification($reservation, $refundAmount));
        }

        return $reservation;
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
}
