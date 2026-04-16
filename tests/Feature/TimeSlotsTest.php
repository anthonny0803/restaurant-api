<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\RestaurantSetting;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeSlotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RestaurantSettingSeeder::class);
    }

    private function queryParams(array $overrides = []): array
    {
        return array_merge([
            'date' => now()->addDays(3)->format('Y-m-d'),
            'seats_requested' => 2,
        ], $overrides);
    }

    // ── Slot generation ─────────────────────────────────────

    public function test_returns_all_slots_between_opening_and_closing(): void
    {
        RestaurantSetting::first()->update([
            'opening_time' => '09:00',
            'closing_time' => '23:00',
            'time_slot_interval_minutes' => 30,
            'default_reservation_duration_minutes' => 60,
        ]);

        Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 4]);

        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query($this->queryParams()));

        // 09:00 to 22:00 stepping by 30 = 27 slots (22:30+60=23:30 > 23:00, excluded)
        $response->assertStatus(200)
            ->assertJsonCount(27, 'data')
            ->assertJsonPath('data.0.start_time', '09:00')
            ->assertJsonPath('data.0.status', 'available')
            ->assertJsonPath('data.26.start_time', '22:00');
    }

    public function test_returns_slots_with_60_min_interval(): void
    {
        RestaurantSetting::first()->update([
            'opening_time' => '10:00',
            'closing_time' => '22:00',
            'time_slot_interval_minutes' => 60,
            'default_reservation_duration_minutes' => 60,
        ]);

        Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 4]);

        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query($this->queryParams()));

        // 10:00 to 21:00 stepping by 60 = 12 slots
        $response->assertStatus(200)
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('data.0.start_time', '10:00')
            ->assertJsonPath('data.11.start_time', '21:00');
    }

    // ── Availability status ─────────────────────────────────

    public function test_slot_blocked_when_all_tables_have_confirmed_reservations(): void
    {
        RestaurantSetting::first()->update([
            'opening_time' => '18:00',
            'closing_time' => '22:00',
            'time_slot_interval_minutes' => 30,
            'default_reservation_duration_minutes' => 60,
        ]);

        $date = now()->addDays(3)->format('Y-m-d');

        $table1 = Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 4]);
        $table2 = Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 4]);

        Reservation::factory()->confirmed()->create([
            'table_id' => $table1->id,
            'date' => $date,
            'start_time' => '19:00:00',
            'end_time' => '20:00:00',
        ]);

        Reservation::factory()->confirmed()->create([
            'table_id' => $table2->id,
            'date' => $date,
            'start_time' => '19:00:00',
            'end_time' => '20:00:00',
        ]);

        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query($this->queryParams([
            'date' => $date,
        ])));

        $response->assertStatus(200);

        $slots = collect($response->json('data'));

        // 19:00 slot (19:00-20:00) overlaps both reservations (19:00-20:00) → blocked
        $this->assertEquals('blocked', $slots->firstWhere('start_time', '19:00')['status']);

        // 19:30 slot (19:30-20:30) overlaps both reservations (19:00-20:00) → blocked
        $this->assertEquals('blocked', $slots->firstWhere('start_time', '19:30')['status']);

        // 18:00 slot (18:00-19:00) does not overlap → available
        $this->assertEquals('available', $slots->firstWhere('start_time', '18:00')['status']);

        // 20:00 slot (20:00-21:00) does not overlap → available
        $this->assertEquals('available', $slots->firstWhere('start_time', '20:00')['status']);
    }

    public function test_slot_available_when_at_least_one_table_free(): void
    {
        RestaurantSetting::first()->update([
            'opening_time' => '18:00',
            'closing_time' => '22:00',
            'time_slot_interval_minutes' => 30,
            'default_reservation_duration_minutes' => 60,
        ]);

        $date = now()->addDays(3)->format('Y-m-d');

        $table1 = Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 4]);
        Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 4]);

        Reservation::factory()->confirmed()->create([
            'table_id' => $table1->id,
            'date' => $date,
            'start_time' => '19:00:00',
            'end_time' => '20:00:00',
        ]);

        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query($this->queryParams([
            'date' => $date,
        ])));

        $slots = collect($response->json('data'));

        $this->assertEquals('available', $slots->firstWhere('start_time', '19:00')['status']);
    }

    public function test_pending_reservations_do_not_block_slots(): void
    {
        RestaurantSetting::first()->update([
            'opening_time' => '18:00',
            'closing_time' => '22:00',
            'time_slot_interval_minutes' => 30,
            'default_reservation_duration_minutes' => 60,
        ]);

        $date = now()->addDays(3)->format('Y-m-d');

        $table = Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 4]);

        Reservation::factory()->pending()->create([
            'table_id' => $table->id,
            'date' => $date,
            'start_time' => '19:00:00',
            'end_time' => '20:00:00',
        ]);

        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query($this->queryParams([
            'date' => $date,
        ])));

        $slots = collect($response->json('data'));

        $this->assertEquals('available', $slots->firstWhere('start_time', '19:00')['status']);
    }

    public function test_cancelled_and_expired_reservations_do_not_block_slots(): void
    {
        RestaurantSetting::first()->update([
            'opening_time' => '18:00',
            'closing_time' => '22:00',
            'time_slot_interval_minutes' => 30,
            'default_reservation_duration_minutes' => 60,
        ]);

        $date = now()->addDays(3)->format('Y-m-d');

        $table = Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 4]);

        Reservation::factory()->cancelled()->create([
            'table_id' => $table->id,
            'date' => $date,
            'start_time' => '19:00:00',
            'end_time' => '20:00:00',
        ]);

        Reservation::factory()->expired()->create([
            'table_id' => $table->id,
            'date' => $date,
            'start_time' => '19:00:00',
            'end_time' => '20:00:00',
        ]);

        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query($this->queryParams([
            'date' => $date,
        ])));

        $slots = collect($response->json('data'));

        $this->assertEquals('available', $slots->firstWhere('start_time', '19:00')['status']);
    }

    // ── Past slots ──────────────────────────────────────────

    public function test_past_slots_today_are_blocked(): void
    {
        $this->travelTo(now()->setTime(15, 0));

        RestaurantSetting::first()->update([
            'opening_time' => '09:00',
            'closing_time' => '23:00',
            'time_slot_interval_minutes' => 30,
            'default_reservation_duration_minutes' => 60,
        ]);

        Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 4]);

        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query($this->queryParams([
            'date' => now()->format('Y-m-d'),
        ])));

        $slots = collect($response->json('data'));

        $this->assertEquals('blocked', $slots->firstWhere('start_time', '14:30')['status']);
        $this->assertEquals('blocked', $slots->firstWhere('start_time', '15:00')['status']);
        $this->assertEquals('available', $slots->firstWhere('start_time', '15:30')['status']);
    }

    // ── Edge cases ──────────────────────────────────────────

    public function test_returns_empty_when_no_tables_match_capacity(): void
    {
        Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 4]);

        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query($this->queryParams([
            'seats_requested' => 20,
        ])));

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_slot_with_90_min_duration_blocks_three_intervals(): void
    {
        RestaurantSetting::first()->update([
            'opening_time' => '18:00',
            'closing_time' => '22:00',
            'time_slot_interval_minutes' => 30,
            'default_reservation_duration_minutes' => 90,
        ]);

        $date = now()->addDays(3)->format('Y-m-d');

        $table = Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 4]);

        // Reservation from 19:00 to 20:30 (90 min)
        Reservation::factory()->confirmed()->create([
            'table_id' => $table->id,
            'date' => $date,
            'start_time' => '19:00:00',
            'end_time' => '20:30:00',
        ]);

        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query($this->queryParams([
            'date' => $date,
        ])));

        $slots = collect($response->json('data'));

        // Slots that overlap with 19:00-20:30: 18:00(18:00-19:30), 18:30(18:30-20:00), 19:00(19:00-20:30), 19:30(19:30-21:00), 20:00(20:00-21:30)
        $this->assertEquals('blocked', $slots->firstWhere('start_time', '18:00')['status']);
        $this->assertEquals('blocked', $slots->firstWhere('start_time', '18:30')['status']);
        $this->assertEquals('blocked', $slots->firstWhere('start_time', '19:00')['status']);
        $this->assertEquals('blocked', $slots->firstWhere('start_time', '19:30')['status']);
        $this->assertEquals('blocked', $slots->firstWhere('start_time', '20:00')['status']);

        // 20:30 slot (20:30-22:00) does not overlap with 19:00-20:30 → available
        $this->assertEquals('available', $slots->firstWhere('start_time', '20:30')['status']);
    }

    // ── Validation ──────────────────────────────────────────

    public function test_rejects_missing_date(): void
    {
        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query([
            'seats_requested' => 2,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_rejects_missing_seats_requested(): void
    {
        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query([
            'date' => now()->addDays(3)->format('Y-m-d'),
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['seats_requested']);
    }

    public function test_rejects_past_date(): void
    {
        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query($this->queryParams([
            'date' => now()->subDay()->format('Y-m-d'),
        ])));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_rejects_date_beyond_7_days(): void
    {
        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query($this->queryParams([
            'date' => now()->addDays(8)->format('Y-m-d'),
        ])));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    // ── Access ───────────────────────────────────────────────

    public function test_endpoint_is_public(): void
    {
        Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 4]);

        $response = $this->getJson('/api/reservations/time-slots?' . http_build_query($this->queryParams()));

        $response->assertStatus(200);
    }
}
