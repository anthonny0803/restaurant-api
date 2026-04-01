<?php

namespace Tests\Feature;

use App\Jobs\ExpireReservationJob;
use App\Models\Payment;
use App\Models\Table;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GuestReservationTest extends TestCase
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

    private function guestHoldData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Perez',
            'email' => 'juan@example.com',
            'phone' => '+34612345678',
            'table_id' => Table::factory()->create()->id,
            'seats_requested' => 2,
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '20:00',
        ], $overrides);
    }

    private function mockPaymentIntent(): void
    {
        $this->paymentServiceMock
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'payment' => new Payment([
                    'amount' => 10.00,
                    'status' => Payment::STATUS_PENDING,
                    'payment_gateway_id' => 'pi_test_guest',
                ]),
                'client_secret' => 'pi_test_guest_secret',
            ]);
    }

    public function test_guest_can_hold_a_reservation(): void
    {
        Queue::fake();
        $this->mockPaymentIntent();

        $table = Table::factory()->create();

        $response = $this->postJson('/api/guest/reservations', $this->guestHoldData([
            'table_id' => $table->id,
        ]));

        $response->assertStatus(201)
            ->assertJsonStructure([
                'reservation' => ['id', 'table', 'seats_requested', 'date', 'start_time', 'status', 'expires_at'],
                'client_secret',
            ])
            ->assertJsonPath('reservation.status', 'pending');

        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'password' => null,
        ]);

        $this->assertDatabaseHas('client_profiles', [
            'phone' => '+34612345678',
        ]);

        $this->assertDatabaseHas('reservations', [
            'table_id' => $table->id,
            'status' => 'pending',
        ]);

        Queue::assertPushed(ExpireReservationJob::class);
    }

    public function test_guest_hold_rejects_email_with_registered_account(): void
    {
        $this->paymentServiceMock->shouldNotReceive('createPaymentIntent');

        $registeredUser = User::factory()->create(['email' => 'registered@example.com']);
        $registeredUser->assignRole('client');

        $response = $this->postJson('/api/guest/reservations', $this->guestHoldData([
            'email' => 'registered@example.com',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_guest_hold_reuses_existing_lazy_user(): void
    {
        Queue::fake();
        $this->mockPaymentIntent();

        $lazyUser = User::create(['name' => 'Juan Perez', 'email' => 'lazy@example.com']);
        $lazyUser->assignRole('client');
        $lazyUser->clientProfile()->create(['phone' => '+34612345678']);

        $table = Table::factory()->create();

        $response = $this->postJson('/api/guest/reservations', $this->guestHoldData([
            'email' => 'lazy@example.com',
            'table_id' => $table->id,
        ]));

        $response->assertStatus(201);

        $this->assertDatabaseCount('users', 1);

        $this->assertDatabaseHas('reservations', [
            'user_id' => $lazyUser->id,
            'table_id' => $table->id,
            'status' => 'pending',
        ]);
    }

    public function test_guest_hold_rejects_missing_required_fields(): void
    {
        $this->paymentServiceMock->shouldNotReceive('createPaymentIntent');

        $response = $this->postJson('/api/guest/reservations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'table_id', 'seats_requested', 'date', 'start_time']);
    }

    public function test_guest_hold_rejects_if_lazy_user_has_pending_reservation(): void
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
        $secondTable = Table::factory()->create();

        $this->postJson('/api/guest/reservations', $this->guestHoldData([
            'table_id' => $table->id,
        ]));

        $response = $this->postJson('/api/guest/reservations', $this->guestHoldData([
            'table_id' => $secondTable->id,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation']);
    }

    public function test_guest_hold_rejects_start_time_not_aligned_to_time_slot_interval(): void
    {
        $this->paymentServiceMock->shouldNotReceive('createPaymentIntent');

        $response = $this->postJson('/api/guest/reservations', $this->guestHoldData([
            'start_time' => '20:15',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    }
}
