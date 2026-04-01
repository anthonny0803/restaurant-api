<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\ReservationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class PreOrderTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    // ── Index ─────────────────────────────────────────────────

    public function test_client_can_list_pre_orders_of_own_reservation(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->create();

        ReservationItem::factory()->create([
            'reservation_id' => $reservation->id,
            'menu_item_id' => $menuItem->id,
            'unit_price' => $menuItem->price,
        ]);

        $response = $this->actingAs($client)
            ->getJson("/api/reservations/{$reservation->id}/pre-orders");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'quantity',
                    'unit_price',
                    'subtotal',
                    'menu_item' => ['id', 'name', 'category'],
                    'created_at',
                ]],
            ]);
    }

    public function test_list_returns_empty_when_no_pre_orders(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);

        $response = $this->actingAs($client)
            ->getJson("/api/reservations/{$reservation->id}/pre-orders");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // ── Store ─────────────────────────────────────────────────

    public function test_client_can_add_pre_order_to_confirmed_reservation(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->create(['price' => '12.50', 'daily_stock' => 10]);

        $response = $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/pre-orders", [
                'menu_item_id' => $menuItem->id,
                'quantity' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.unit_price', '12.50')
            ->assertJsonPath('data.subtotal', '25.00')
            ->assertJsonPath('data.menu_item.id', $menuItem->id);

        $this->assertDatabaseHas('reservation_items', [
            'reservation_id' => $reservation->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 2,
            'unit_price' => '12.50',
        ]);
    }

    public function test_store_decrements_daily_stock(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->create(['daily_stock' => 10]);

        $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/pre-orders", [
                'menu_item_id' => $menuItem->id,
                'quantity' => 3,
            ]);

        $this->assertDatabaseHas('menu_items', [
            'id' => $menuItem->id,
            'daily_stock' => 7,
        ]);
    }

    public function test_store_does_not_decrement_unlimited_stock(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->unlimitedStock()->create();

        $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/pre-orders", [
                'menu_item_id' => $menuItem->id,
                'quantity' => 5,
            ]);

        $this->assertDatabaseHas('menu_items', [
            'id' => $menuItem->id,
            'daily_stock' => null,
        ]);
    }

    public function test_store_rejects_if_reservation_not_confirmed(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->pending()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->create();

        $response = $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/pre-orders", [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    public function test_store_rejects_unavailable_menu_item(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->unavailable()->create();

        $response = $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/pre-orders", [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['menu_item_id']);
    }

    public function test_store_rejects_insufficient_stock(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->create(['daily_stock' => 2]);

        $response = $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/pre-orders", [
                'menu_item_id' => $menuItem->id,
                'quantity' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_store_rejects_duplicate_menu_item(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->create(['daily_stock' => 20]);

        ReservationItem::factory()->create([
            'reservation_id' => $reservation->id,
            'menu_item_id' => $menuItem->id,
            'unit_price' => $menuItem->price,
        ]);

        $response = $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/pre-orders", [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['menu_item_id']);
    }

    public function test_store_rejects_nonexistent_menu_item(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);

        $response = $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/pre-orders", [
                'menu_item_id' => 9999,
                'quantity' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['menu_item_id']);
    }

    public function test_store_rejects_invalid_quantity(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->create();

        $response = $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/pre-orders", [
                'menu_item_id' => $menuItem->id,
                'quantity' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    // ── Destroy ───────────────────────────────────────────────

    public function test_client_can_remove_pre_order(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->create();

        $item = ReservationItem::factory()->create([
            'reservation_id' => $reservation->id,
            'menu_item_id' => $menuItem->id,
            'unit_price' => $menuItem->price,
        ]);

        $response = $this->actingAs($client)
            ->deleteJson("/api/reservations/{$reservation->id}/pre-orders/{$item->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('reservation_items', [
            'id' => $item->id,
        ]);
    }

    public function test_destroy_releases_stock(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->create(['daily_stock' => 7]);

        $item = ReservationItem::factory()->create([
            'reservation_id' => $reservation->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 3,
            'unit_price' => $menuItem->price,
        ]);

        $this->actingAs($client)
            ->deleteJson("/api/reservations/{$reservation->id}/pre-orders/{$item->id}");

        $this->assertDatabaseHas('menu_items', [
            'id' => $menuItem->id,
            'daily_stock' => 10,
        ]);
    }

    public function test_destroy_does_not_modify_unlimited_stock(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->unlimitedStock()->create();

        $item = ReservationItem::factory()->create([
            'reservation_id' => $reservation->id,
            'menu_item_id' => $menuItem->id,
            'unit_price' => $menuItem->price,
        ]);

        $this->actingAs($client)
            ->deleteJson("/api/reservations/{$reservation->id}/pre-orders/{$item->id}");

        $this->assertDatabaseHas('menu_items', [
            'id' => $menuItem->id,
            'daily_stock' => null,
        ]);
    }

    public function test_destroy_rejects_if_reservation_not_confirmed(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->create();

        $item = ReservationItem::factory()->create([
            'reservation_id' => $reservation->id,
            'menu_item_id' => $menuItem->id,
            'unit_price' => $menuItem->price,
        ]);

        $reservation->update(['status' => Reservation::STATUS_COMPLETED]);

        $response = $this->actingAs($client)
            ->deleteJson("/api/reservations/{$reservation->id}/pre-orders/{$item->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    // ── Authorization ─────────────────────────────────────────

    public function test_client_cannot_access_others_reservation_pre_orders(): void
    {
        $owner = $this->clientUser();
        $other = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)
            ->getJson("/api/reservations/{$reservation->id}/pre-orders");

        $response->assertStatus(403);
    }

    public function test_client_cannot_add_pre_order_to_others_reservation(): void
    {
        $owner = $this->clientUser();
        $other = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $owner->id]);
        $menuItem = MenuItem::factory()->create();

        $response = $this->actingAs($other)
            ->postJson("/api/reservations/{$reservation->id}/pre-orders", [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(403);
    }

    public function test_client_cannot_delete_pre_order_from_others_reservation(): void
    {
        $owner = $this->clientUser();
        $other = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $owner->id]);
        $menuItem = MenuItem::factory()->create();

        $item = ReservationItem::factory()->create([
            'reservation_id' => $reservation->id,
            'menu_item_id' => $menuItem->id,
            'unit_price' => $menuItem->price,
        ]);

        $response = $this->actingAs($other)
            ->deleteJson("/api/reservations/{$reservation->id}/pre-orders/{$item->id}");

        $response->assertStatus(403);
    }

    public function test_destroy_rejects_item_from_another_reservation(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $otherReservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        $menuItem = MenuItem::factory()->create();

        $item = ReservationItem::factory()->create([
            'reservation_id' => $otherReservation->id,
            'menu_item_id' => $menuItem->id,
            'unit_price' => $menuItem->price,
        ]);

        $response = $this->actingAs($client)
            ->deleteJson("/api/reservations/{$reservation->id}/pre-orders/{$item->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('reservation_items', ['id' => $item->id]);
    }

    public function test_unauthenticated_user_cannot_access_pre_orders(): void
    {
        $reservation = Reservation::factory()->confirmed()->create();

        $this->getJson("/api/reservations/{$reservation->id}/pre-orders")
            ->assertStatus(401);

        $this->postJson("/api/reservations/{$reservation->id}/pre-orders", [])
            ->assertStatus(401);
    }
}
