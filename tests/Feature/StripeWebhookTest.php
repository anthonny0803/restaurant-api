<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Event;
use Stripe\Webhook;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\RestaurantSettingSeeder::class);
    }

    private function createReservationWithPayment(string $status = Reservation::STATUS_PENDING): array
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $table = Table::create([
            'name' => 'Mesa ' . uniqid(),
            'min_capacity' => 2,
            'max_capacity' => 4,
            'location' => 'interior',
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'table_id' => $table->id,
            'seats_requested' => 2,
            'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '20:00:00',
            'end_time' => '22:00:00',
            'status' => $status,
            'expires_at' => now()->addMinutes(15),
        ]);

        $reservation->cancellationPolicySnapshot()->create([
            'cancellation_deadline_hours' => 24,
            'refund_percentage' => 50,
            'admin_fee_percentage' => 10,
            'policy_accepted_at' => now(),
        ]);

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'amount' => 10.00,
            'status' => Payment::STATUS_PENDING,
            'payment_gateway_id' => 'pi_test_' . uniqid(),
        ]);

        return [$reservation, $payment];
    }

    private function webhookPayload(string $gatewayId): string
    {
        return json_encode([
            'id' => 'evt_test_' . uniqid(),
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $gatewayId,
                    'object' => 'payment_intent',
                    'amount' => 1000,
                    'currency' => 'eur',
                    'status' => 'succeeded',
                ],
            ],
        ]);
    }

    public function test_webhook_confirms_reservation_on_payment_succeeded(): void
    {
        [$reservation, $payment] = $this->createReservationWithPayment();

        $payload = $this->webhookPayload($payment->payment_gateway_id);

        $mock = \Mockery::mock('alias:' . Webhook::class);
        $mock->shouldReceive('constructEvent')
            ->once()
            ->andReturn(Event::constructFrom(json_decode($payload, true)));

        $response = $this->postJson('/api/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=fake_signature',
        ]);

        $response->assertStatus(200)
            ->assertJson(['received' => true]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'succeeded',
        ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $mock = \Mockery::mock('alias:' . Webhook::class);
        $mock->shouldReceive('constructEvent')
            ->once()
            ->andThrow(new \Stripe\Exception\SignatureVerificationException('Invalid signature'));

        $response = $this->postJson('/api/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=invalid',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'Firma invalida.']);
    }

    public function test_webhook_is_idempotent_for_already_confirmed_reservation(): void
    {
        [$reservation, $payment] = $this->createReservationWithPayment(Reservation::STATUS_CONFIRMED);

        $payment->update([
            'status' => Payment::STATUS_SUCCEEDED,
            'paid_at' => now(),
        ]);

        $payload = $this->webhookPayload($payment->payment_gateway_id);

        $mock = \Mockery::mock('alias:' . Webhook::class);
        $mock->shouldReceive('constructEvent')
            ->once()
            ->andReturn(Event::constructFrom(json_decode($payload, true)));

        $response = $this->postJson('/api/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=fake_signature',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
        ]);
    }
}
