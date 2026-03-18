<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'table_id' => Table::factory(),
            'seats_requested' => 2,
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '20:00:00',
            'end_time' => '22:00:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'expires_at' => now()->addMinutes(15),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => Reservation::STATUS_PENDING]);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => Reservation::STATUS_CONFIRMED]);
    }

    public function expired(): static
    {
        return $this->state(['status' => Reservation::STATUS_EXPIRED]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => Reservation::STATUS_CANCELLED]);
    }

    public function withCancellationPolicy(): static
    {
        return $this->afterCreating(function (Reservation $reservation) {
            $reservation->cancellationPolicySnapshot()->create([
                'cancellation_deadline_hours' => 24,
                'refund_percentage' => 50,
                'admin_fee_percentage' => 10,
                'policy_accepted_at' => now(),
            ]);
        });
    }
}
