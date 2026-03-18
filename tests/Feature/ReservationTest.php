<?php

namespace Tests\Feature;

use App\Jobs\ExpireReservationJob;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class ReservationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    private PaymentService&MockInterface $paymentServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RestaurantSettingSeeder::class);

        $this->paymentServiceMock = Mockery::mock(PaymentService::class);
        $this->app->instance(PaymentService::class, $this->paymentServiceMock);
    }

    private function holdData(array $overrides = []): array
    {
        return array_merge([
            'table_id' => Table::factory()->create()->id,
            'seats_requested' => 2,
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '20:00',
        ], $overrides);
    }

    // ── Hold (store) ─────────────────────────────────────────

    public function test_client_can_hold_a_reservation(): void
    {
        Queue::fake();

        $this->paymentServiceMock
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'payment' => new Payment([
                    'amount' => 10.00,
                    'status' => Payment::STATUS_PENDING,
                    'payment_gateway_id' => 'pi_test_123',
                ]),
                'client_secret' => 'pi_test_123_secret',
            ]);

        $table = Table::factory()->create();

        $response = $this->actingAs($this->clientUser())
            ->postJson('/api/reservations', [
                'table_id' => $table->id,
                'seats_requested' => 2,
                'date' => now()->addDays(3)->format('Y-m-d'),
                'start_time' => '20:00',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'reservation' => ['id', 'table', 'seats_requested', 'date', 'start_time', 'status', 'expires_at'],
                'client_secret',
            ])
            ->assertJsonPath('reservation.status', 'pending')
            ->assertJsonPath('client_secret', 'pi_test_123_secret');

        $this->assertDatabaseHas('reservations', [
            'table_id' => $table->id,
            'status' => 'pending',
        ]);

        Queue::assertPushed(ExpireReservationJob::class);
    }

    public function test_hold_rejects_inactive_table(): void
    {
        $this->paymentServiceMock->shouldNotReceive('createPaymentIntent');

        $table = Table::factory()->inactive()->create();

        $response = $this->actingAs($this->clientUser())
            ->postJson('/api/reservations', [
                'table_id' => $table->id,
                'seats_requested' => 2,
                'date' => now()->addDays(3)->format('Y-m-d'),
                'start_time' => '20:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);
    }

    public function test_hold_rejects_seats_outside_table_capacity(): void
    {
        $this->paymentServiceMock->shouldNotReceive('createPaymentIntent');

        $table = Table::factory()->create(['min_capacity' => 2, 'max_capacity' => 4]);

        $response = $this->actingAs($this->clientUser())
            ->postJson('/api/reservations', [
                'table_id' => $table->id,
                'seats_requested' => 6,
                'date' => now()->addDays(3)->format('Y-m-d'),
                'start_time' => '20:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['seats_requested']);
    }

    public function test_hold_rejects_past_date(): void
    {
        $this->paymentServiceMock->shouldNotReceive('createPaymentIntent');

        $response = $this->actingAs($this->clientUser())
            ->postJson('/api/reservations', $this->holdData([
                'date' => now()->subDay()->format('Y-m-d'),
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_hold_rejects_date_beyond_one_week(): void
    {
        $this->paymentServiceMock->shouldNotReceive('createPaymentIntent');

        $response = $this->actingAs($this->clientUser())
            ->postJson('/api/reservations', $this->holdData([
                'date' => now()->addDays(8)->format('Y-m-d'),
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_hold_rejects_if_user_already_has_pending_reservation(): void
    {
        Queue::fake();

        $this->paymentServiceMock
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'payment' => new Payment([
                    'amount' => 10.00,
                    'status' => Payment::STATUS_PENDING,
                    'payment_gateway_id' => 'pi_test_first',
                ]),
                'client_secret' => 'pi_test_first_secret',
            ]);

        $client = $this->clientUser();
        $table = Table::factory()->create();
        $date = now()->addDays(3)->format('Y-m-d');

        $this->actingAs($client)->postJson('/api/reservations', [
            'table_id' => $table->id,
            'seats_requested' => 2,
            'date' => $date,
            'start_time' => '20:00',
        ]);

        $secondTable = Table::factory()->create();

        $response = $this->actingAs($client)
            ->postJson('/api/reservations', [
                'table_id' => $secondTable->id,
                'seats_requested' => 2,
                'date' => $date,
                'start_time' => '20:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    public function test_hold_rejects_overlapping_reservation_on_same_table(): void
    {
        Queue::fake();

        $this->paymentServiceMock
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'payment' => new Payment([
                    'amount' => 10.00,
                    'status' => Payment::STATUS_PENDING,
                    'payment_gateway_id' => 'pi_test_first',
                ]),
                'client_secret' => 'pi_test_first_secret',
            ]);

        $table = Table::factory()->create();
        $date = now()->addDays(3)->format('Y-m-d');

        $firstClient = $this->clientUser();
        $this->actingAs($firstClient)
            ->postJson('/api/reservations', [
                'table_id' => $table->id,
                'seats_requested' => 2,
                'date' => $date,
                'start_time' => '20:00',
            ]);

        $secondClient = $this->clientUser();
        $response = $this->actingAs($secondClient)
            ->postJson('/api/reservations', [
                'table_id' => $table->id,
                'seats_requested' => 2,
                'date' => $date,
                'start_time' => '21:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);
    }

    public function test_admin_cannot_create_reservation(): void
    {
        $this->paymentServiceMock->shouldNotReceive('createPaymentIntent');

        $response = $this->actingAs($this->adminUser())
            ->postJson('/api/reservations', $this->holdData());

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_create_reservation(): void
    {
        $response = $this->postJson('/api/reservations', $this->holdData());

        $response->assertStatus(401);
    }

    // ── Index ────────────────────────────────────────────────

    public function test_client_sees_only_own_reservations(): void
    {
        $client = $this->clientUser();
        $otherClient = $this->clientUser();
        $table = Table::factory()->create();

        Reservation::factory()->withCancellationPolicy()->create([
            'user_id' => $client->id,
            'table_id' => $table->id,
        ]);
        Reservation::factory()->withCancellationPolicy()->create([
            'user_id' => $otherClient->id,
            'table_id' => $table->id,
            'date' => now()->addDays(4)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($client)
            ->getJson('/api/reservations');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_sees_all_reservations(): void
    {
        $table = Table::factory()->create();

        Reservation::factory()->withCancellationPolicy()->create(['table_id' => $table->id]);
        Reservation::factory()->withCancellationPolicy()->create([
            'table_id' => $table->id,
            'date' => now()->addDays(4)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/reservations');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    // ── Show ─────────────────────────────────────────────────

    public function test_client_can_view_own_reservation(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->withCancellationPolicy()->create([
            'user_id' => $client->id,
        ]);

        $response = $this->actingAs($client)
            ->getJson("/api/reservations/{$reservation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $reservation->id);
    }

    public function test_client_cannot_view_others_reservation(): void
    {
        $reservation = Reservation::factory()->withCancellationPolicy()->create();

        $response = $this->actingAs($this->clientUser())
            ->getJson("/api/reservations/{$reservation->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_view_any_reservation(): void
    {
        $reservation = Reservation::factory()->withCancellationPolicy()->create();

        $response = $this->actingAs($this->adminUser())
            ->getJson("/api/admin/reservations/{$reservation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $reservation->id);
    }

    // ── Cancel ───────────────────────────────────────────────

    public function test_client_can_cancel_own_confirmed_reservation(): void
    {
        $this->paymentServiceMock
            ->shouldReceive('refund')
            ->once();

        $client = $this->clientUser();
        $reservation = Reservation::factory()->withCancellationPolicy()->create([
            'user_id' => $client->id,
        ]);

        Payment::factory()->succeeded()->create(['reservation_id' => $reservation->id]);

        $response = $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_pending_reservation_without_payment(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->pending()->withCancellationPolicy()->create([
            'user_id' => $client->id,
        ]);

        $this->paymentServiceMock->shouldNotReceive('refund');

        $response = $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cannot_cancel_already_expired_reservation(): void
    {
        $client = $this->clientUser();
        $reservation = Reservation::factory()->expired()->withCancellationPolicy()->create([
            'user_id' => $client->id,
        ]);

        $response = $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/cancel");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    public function test_client_cannot_cancel_others_reservation(): void
    {
        $reservation = Reservation::factory()->withCancellationPolicy()->create();

        $response = $this->actingAs($this->clientUser())
            ->postJson("/api/reservations/{$reservation->id}/cancel");

        $response->assertStatus(403);
    }
}
