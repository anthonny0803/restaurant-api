<?php

namespace Tests\Feature;

use App\Jobs\AutoCompleteReservationsJob;
use App\Models\Reservation;
use App\Repositories\ReservationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCompleteReservationsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RestaurantSettingSeeder::class);
    }

    private function runJob(): void
    {
        (new AutoCompleteReservationsJob())->handle(
            app(ReservationRepository::class),
        );
    }

    public function test_completes_confirmed_reservation_past_end_time(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'date' => now()->subDay()->format('Y-m-d'),
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
        ]);

        $this->runJob();

        $this->assertSame(Reservation::STATUS_COMPLETED, $reservation->fresh()->status);
    }

    public function test_does_not_complete_confirmed_reservation_still_in_progress(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
        ]);

        $this->runJob();

        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->fresh()->status);
    }

    public function test_does_not_complete_reservations_with_non_confirmed_statuses(): void
    {
        $yesterday = now()->subDay()->format('Y-m-d');

        $pending = Reservation::factory()->pending()->create([
            'date' => $yesterday,
            'end_time' => '20:00:00',
        ]);

        $cancelled = Reservation::factory()->cancelled()->create([
            'date' => $yesterday,
            'end_time' => '20:00:00',
        ]);

        $expired = Reservation::factory()->expired()->create([
            'date' => $yesterday,
            'end_time' => '20:00:00',
        ]);

        $this->runJob();

        $this->assertSame(Reservation::STATUS_PENDING, $pending->fresh()->status);
        $this->assertSame(Reservation::STATUS_CANCELLED, $cancelled->fresh()->status);
        $this->assertSame(Reservation::STATUS_EXPIRED, $expired->fresh()->status);
    }

    public function test_completes_multiple_reservations_in_single_run(): void
    {
        $yesterday = now()->subDay()->format('Y-m-d');

        $first = Reservation::factory()->confirmed()->create([
            'date' => $yesterday,
            'end_time' => '20:00:00',
        ]);

        $second = Reservation::factory()->confirmed()->create([
            'date' => $yesterday,
            'end_time' => '22:00:00',
        ]);

        $this->runJob();

        $this->assertSame(Reservation::STATUS_COMPLETED, $first->fresh()->status);
        $this->assertSame(Reservation::STATUS_COMPLETED, $second->fresh()->status);
    }
}
