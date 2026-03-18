<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'amount' => 10.00,
            'status' => Payment::STATUS_PENDING,
            'payment_gateway_id' => 'pi_test_' . $this->faker->unique()->bothify('??########'),
        ];
    }

    public function succeeded(): static
    {
        return $this->state([
            'status' => Payment::STATUS_SUCCEEDED,
            'paid_at' => now(),
        ]);
    }
}
