<?php

namespace Tests\Feature;

use App\Jobs\ExpireReservationJob;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService&MockInterface $paymentServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RestaurantSettingSeeder::class);

        $this->paymentServiceMock = Mockery::mock(PaymentService::class);
        $this->app->instance(PaymentService::class, $this->paymentServiceMock);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function createTable(array $overrides = []): Table
    {
        return Table::create(array_merge([
            'name' => 'Mesa ' . uniqid(),
            'min_capacity' => 2,
            'max_capacity' => 4,
            'location' => 'interior',
            'is_active' => true,
        ], $overrides));
    }

    private function holdData(array $overrides = []): array
    {
        return array_merge([
            'table_id' => $this->createTable()->id,
            'seats_requested' => 2,
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '20:00',
        ], $overrides);
    }

    private function createReservation(User $user, Table $table, array $overrides = []): Reservation
    {
        $reservation = Reservation::create(array_merge([
            'user_id' => $user->id,
            'table_id' => $table->id,
            'seats_requested' => 2,
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '20:00:00',
            'end_time' => '22:00:00',
            'status' => Reservation::STATUS_CONFIRMED,
            'expires_at' => now()->addMinutes(15),
        ], $overrides));

        $reservation->cancellationPolicySnapshot()->create([
            'cancellation_deadline_hours' => 24,
            'refund_percentage' => 50,
            'admin_fee_percentage' => 10,
            'policy_accepted_at' => now(),
        ]);

        return $reservation;
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

        $table = $this->createTable();

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

        $table = $this->createTable(['is_active' => false]);

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

        $table = $this->createTable(['min_capacity' => 2, 'max_capacity' => 4]);

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
        $table = $this->createTable();
        $date = now()->addDays(3)->format('Y-m-d');

        $this->actingAs($client)->postJson('/api/reservations', [
            'table_id' => $table->id,
            'seats_requested' => 2,
            'date' => $date,
            'start_time' => '20:00',
        ]);

        $secondTable = $this->createTable();

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

        $table = $this->createTable();
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
        $table = $this->createTable();

        $this->createReservation($client, $table);
        $this->createReservation($otherClient, $table, [
            'date' => now()->addDays(4)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($client)
            ->getJson('/api/reservations');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_sees_all_reservations(): void
    {
        $table = $this->createTable();

        $this->createReservation($this->clientUser(), $table);
        $this->createReservation($this->clientUser(), $table, [
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
        $table = $this->createTable();
        $reservation = $this->createReservation($client, $table);

        $response = $this->actingAs($client)
            ->getJson("/api/reservations/{$reservation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $reservation->id);
    }

    public function test_client_cannot_view_others_reservation(): void
    {
        $table = $this->createTable();
        $reservation = $this->createReservation($this->clientUser(), $table);

        $response = $this->actingAs($this->clientUser())
            ->getJson("/api/reservations/{$reservation->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_view_any_reservation(): void
    {
        $table = $this->createTable();
        $reservation = $this->createReservation($this->clientUser(), $table);

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
        $table = $this->createTable();
        $reservation = $this->createReservation($client, $table);

        $reservation->payment()->create([
            'amount' => 10.00,
            'status' => Payment::STATUS_SUCCEEDED,
            'payment_gateway_id' => 'pi_test_cancel',
            'paid_at' => now(),
        ]);

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
        $table = $this->createTable();
        $reservation = $this->createReservation($client, $table, [
            'status' => Reservation::STATUS_PENDING,
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
        $table = $this->createTable();
        $reservation = $this->createReservation($client, $table, [
            'status' => Reservation::STATUS_EXPIRED,
        ]);

        $response = $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/cancel");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    public function test_client_cannot_cancel_others_reservation(): void
    {
        $table = $this->createTable();
        $reservation = $this->createReservation($this->clientUser(), $table);

        $response = $this->actingAs($this->clientUser())
            ->postJson("/api/reservations/{$reservation->id}/cancel");

        $response->assertStatus(403);
    }
}
