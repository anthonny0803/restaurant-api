<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Reservation;
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
        $reservation = Reservation::factory()
            ->withCancellationPolicy()
            ->create(['status' => $status]);

        $payment = Payment::factory()->create(['reservation_id' => $reservation->id]);

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
