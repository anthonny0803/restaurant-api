<?php

namespace Database\Factories;

use App\Models\CancellationPolicySnapshot;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CancellationPolicySnapshot>
 */
class CancellationPolicySnapshotFactory extends Factory
{
    protected $model = CancellationPolicySnapshot::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'cancellation_deadline_hours' => 24,
            'refund_percentage' => 50,
            'admin_fee_percentage' => 10,
            'policy_accepted_at' => now(),
        ];
    }
}
