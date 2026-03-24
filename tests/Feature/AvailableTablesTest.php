<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailableTablesTest extends TestCase
{
    use RefreshDatabase;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RestaurantSettingSeeder::class);

        $this->date = now()->addDays(3)->format('Y-m-d');
    }

    private function queryParams(array $overrides = []): array
    {
        return array_merge([
            'seats_requested' => 4,
            'date' => $this->date,
            'start_time' => '20:00',
        ], $overrides);
    }

    // ── Filtering ────────────────────────────────────────────

    public function test_returns_available_tables_matching_capacity(): void
    {
        Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 6]);
        Table::factory()->create(['min_capacity' => 8, 'max_capacity' => 10]);

        $response = $this->getJson('/api/reservations/available-tables?' . http_build_query($this->queryParams()));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_does_not_return_inactive_tables(): void
    {
        Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 6, 'is_active' => true]);
        Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 6, 'is_active' => false]);

        $response = $this->getJson('/api/reservations/available-tables?' . http_build_query($this->queryParams()));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_does_not_return_tables_with_overlapping_reservation(): void
    {
        $table = Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 6]);

        Reservation::factory()->confirmed()->create([
            'table_id' => $table->id,
            'date' => $this->date,
            'start_time' => '19:00:00',
            'end_time' => '21:00:00',
        ]);

        $response = $this->getJson('/api/reservations/available-tables?' . http_build_query($this->queryParams()));

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_returns_table_when_reservation_does_not_overlap(): void
    {
        $table = Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 6]);

        Reservation::factory()->confirmed()->create([
            'table_id' => $table->id,
            'date' => $this->date,
            'start_time' => '16:00:00',
            'end_time' => '18:00:00',
        ]);

        $response = $this->getJson('/api/reservations/available-tables?' . http_build_query($this->queryParams()));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_ignores_cancelled_and_expired_reservations(): void
    {
        $table = Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 6]);

        Reservation::factory()->cancelled()->create([
            'table_id' => $table->id,
            'date' => $this->date,
            'start_time' => '19:00:00',
            'end_time' => '21:00:00',
        ]);

        Reservation::factory()->expired()->create([
            'table_id' => $table->id,
            'date' => $this->date,
            'start_time' => '19:00:00',
            'end_time' => '21:00:00',
        ]);

        $response = $this->getJson('/api/reservations/available-tables?' . http_build_query($this->queryParams()));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_returns_empty_when_no_tables_available(): void
    {
        $response = $this->getJson('/api/reservations/available-tables?' . http_build_query($this->queryParams()));

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // ── Ordering ─────────────────────────────────────────────

    public function test_orders_by_best_fit(): void
    {
        Table::factory()->create(['name' => 'Grande', 'min_capacity' => 4, 'max_capacity' => 10]);
        Table::factory()->create(['name' => 'Justa', 'min_capacity' => 2, 'max_capacity' => 4]);
        Table::factory()->create(['name' => 'Mediana', 'min_capacity' => 2, 'max_capacity' => 6]);

        $response = $this->getJson('/api/reservations/available-tables?' . http_build_query($this->queryParams()));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Justa')
            ->assertJsonPath('data.1.name', 'Mediana')
            ->assertJsonPath('data.2.name', 'Grande');
    }

    // ── Validation ───────────────────────────────────────────

    public function test_rejects_missing_fields(): void
    {
        $response = $this->getJson('/api/reservations/available-tables');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['seats_requested', 'date', 'start_time']);
    }

    public function test_rejects_past_date(): void
    {
        $response = $this->getJson('/api/reservations/available-tables?' . http_build_query(
            $this->queryParams(['date' => now()->subDay()->format('Y-m-d')])
        ));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_rejects_date_beyond_one_week(): void
    {
        $response = $this->getJson('/api/reservations/available-tables?' . http_build_query(
            $this->queryParams(['date' => now()->addDays(8)->format('Y-m-d')])
        ));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_rejects_start_time_not_aligned_to_interval(): void
    {
        $response = $this->getJson('/api/reservations/available-tables?' . http_build_query(
            $this->queryParams(['start_time' => '20:15'])
        ));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    }

    // ── Access ────────────────────────────────────────────────

    public function test_endpoint_is_public(): void
    {
        Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 6]);

        $response = $this->getJson('/api/reservations/available-tables?' . http_build_query($this->queryParams()));

        $response->assertStatus(200);
    }
}
