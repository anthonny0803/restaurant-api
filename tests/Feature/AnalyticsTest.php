<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    // --- Authorization ---

    public function test_unauthenticated_user_cannot_access_analytics(): void
    {
        $this->getJson('/api/admin/analytics/occupancy')->assertStatus(401);
        $this->getJson('/api/admin/analytics/revenue')->assertStatus(401);
        $this->getJson('/api/admin/analytics/top-menu-items')->assertStatus(401);
    }

    public function test_client_cannot_access_analytics(): void
    {
        $client = $this->clientUser();

        $this->actingAs($client)->getJson('/api/admin/analytics/occupancy')->assertStatus(403);
        $this->actingAs($client)->getJson('/api/admin/analytics/revenue')->assertStatus(403);
        $this->actingAs($client)->getJson('/api/admin/analytics/top-menu-items')->assertStatus(403);
    }

    // --- Validation ---

    public function test_invalid_date_format_returns_422(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->getJson('/api/admin/analytics/occupancy?date_from=15-03-2026')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_from']);
    }

    public function test_date_to_before_date_from_returns_422(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->getJson('/api/admin/analytics/occupancy?date_from=2026-03-20&date_to=2026-03-10')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    // --- Occupancy ---

    public function test_occupancy_returns_correct_structure_and_values(): void
    {
        $table = Table::factory()->create(['name' => 'Mesa 1', 'max_capacity' => 4]);

        Reservation::factory()->confirmed()->create([
            'table_id' => $table->id,
            'seats_requested' => 2,
        ]);
        Reservation::factory()->create([
            'table_id' => $table->id,
            'seats_requested' => 4,
            'date' => '2026-03-16',
            'status' => Reservation::STATUS_COMPLETED,
        ]);
        Reservation::factory()->cancelled()->create();

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/analytics/occupancy');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_reservations',
                'by_status',
                'average_seats_per_reservation',
                'by_table',
                'peak_days',
                'peak_hours',
            ]);

        $data = $response->json();

        $this->assertEquals(3, $data['total_reservations']);
        $this->assertEquals(1, $data['by_status']['confirmed']);
        $this->assertEquals(1, $data['by_status']['completed']);
        $this->assertEquals(1, $data['by_status']['cancelled']);
        $this->assertEquals(3.0, $data['average_seats_per_reservation']);
    }

    public function test_occupancy_with_no_data_returns_zeros(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/analytics/occupancy');

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(0, $data['total_reservations']);
        $this->assertEquals(0.0, $data['average_seats_per_reservation']);
        $this->assertEmpty($data['by_table']);
        $this->assertEmpty($data['peak_days']);
        $this->assertEmpty($data['peak_hours']);
    }

    // --- Revenue ---

    public function test_revenue_returns_correct_calculations(): void
    {
        $reservation1 = Reservation::factory()->confirmed()->create();
        $reservation2 = Reservation::factory()->cancelled()->create([
            'date' => '2026-03-16',
        ]);

        Payment::factory()->succeeded()->create([
            'reservation_id' => $reservation1->id,
            'amount' => 20.00,
            'refund_amount' => 0,
        ]);

        Payment::factory()->create([
            'reservation_id' => $reservation2->id,
            'amount' => 30.00,
            'status' => Payment::STATUS_PARTIALLY_REFUNDED,
            'refund_amount' => 15.00,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/analytics/revenue');

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(50.00, $data['total_collected']);
        $this->assertEquals(15.00, $data['total_refunded']);
        $this->assertEquals(35.00, $data['net_revenue']);
        $this->assertEquals(2, $data['total_payments']);
        $this->assertEquals(25.00, $data['average_deposit']);
    }

    public function test_revenue_with_no_payments_returns_zeros(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/analytics/revenue');

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(0, $data['total_collected']);
        $this->assertEquals(0, $data['total_refunded']);
        $this->assertEquals(0, $data['net_revenue']);
        $this->assertEquals(0, $data['total_payments']);
        $this->assertEquals(0, $data['average_deposit']);
    }

    // --- Top Menu Items ---

    public function test_top_menu_items_returns_correct_ranking(): void
    {
        $reservation = Reservation::factory()->confirmed()->create();

        $paella = MenuItem::factory()->create([
            'name' => 'Paella',
            'category' => 'principales',
            'price' => 15.00,
        ]);
        $tortilla = MenuItem::factory()->create([
            'name' => 'Tortilla',
            'category' => 'entrantes',
            'price' => 8.00,
        ]);

        ReservationItem::create([
            'reservation_id' => $reservation->id,
            'menu_item_id' => $paella->id,
            'quantity' => 2,
            'unit_price' => 15.00,
        ]);
        ReservationItem::create([
            'reservation_id' => $reservation->id,
            'menu_item_id' => $tortilla->id,
            'quantity' => 5,
            'unit_price' => 8.00,
        ]);

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/analytics/top-menu-items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'top_by_quantity',
                'top_by_revenue',
                'by_category',
            ]);

        $data = $response->json();

        $this->assertEquals('Tortilla', $data['top_by_quantity'][0]['menu_item']);
        $this->assertEquals(5, $data['top_by_quantity'][0]['total_quantity']);

        $this->assertEquals('Tortilla', $data['top_by_revenue'][0]['menu_item']);
        $this->assertEquals(40.00, $data['top_by_revenue'][0]['total_revenue']);

        $this->assertEquals('Paella', $data['top_by_revenue'][1]['menu_item']);
        $this->assertEquals(30.00, $data['top_by_revenue'][1]['total_revenue']);
    }

    public function test_top_menu_items_with_no_orders_returns_empty(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/analytics/top-menu-items');

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEmpty($data['top_by_quantity']);
        $this->assertEmpty($data['top_by_revenue']);
        $this->assertEmpty($data['by_category']);
    }

    // --- Date Filters ---

    public function test_date_filter_only_includes_reservations_in_range(): void
    {
        Reservation::factory()->confirmed()->create([
            'date' => '2026-03-10',
            'seats_requested' => 2,
        ]);
        Reservation::factory()->confirmed()->create([
            'date' => '2026-03-20',
            'seats_requested' => 4,
        ]);

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/analytics/occupancy?date_from=2026-03-18&date_to=2026-03-25');

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(1, $data['total_reservations']);
        $this->assertEquals(1, $data['by_status']['confirmed']);
    }

    public function test_date_filter_works_on_revenue_endpoint(): void
    {
        $inRange = Reservation::factory()->confirmed()->create(['date' => '2026-03-15']);
        $outOfRange = Reservation::factory()->confirmed()->create(['date' => '2026-03-01']);

        Payment::factory()->succeeded()->create([
            'reservation_id' => $inRange->id,
            'amount' => 20.00,
            'refund_amount' => 0,
        ]);
        Payment::factory()->succeeded()->create([
            'reservation_id' => $outOfRange->id,
            'amount' => 50.00,
            'refund_amount' => 0,
        ]);

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/analytics/revenue?date_from=2026-03-10&date_to=2026-03-20');

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(20.00, $data['total_collected']);
        $this->assertEquals(1, $data['total_payments']);
    }
}
