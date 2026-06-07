<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Reservation;
use App\Notifications\ReservationPaymentRefundedNotification;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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

    private function webhookPayload(
        string $gatewayId,
        string $type = 'payment_intent.succeeded',
        ?array $lastPaymentError = null,
    ): string {
        $object = [
            'id' => $gatewayId,
            'object' => 'payment_intent',
            'amount' => 1000,
            'currency' => 'eur',
            'status' => 'succeeded',
        ];

        if ($lastPaymentError !== null) {
            $object['last_payment_error'] = $lastPaymentError;
        }

        return json_encode([
            'id' => 'evt_test_' . uniqid(),
            'type' => $type,
            'data' => [
                'object' => $object,
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

    public function test_webhook_returns_200_when_payment_not_found(): void
    {
        $nonExistentGatewayId = 'pi_non_existent_123';
        $payload = $this->webhookPayload($nonExistentGatewayId);

        $mock = \Mockery::mock('alias:' . Webhook::class);
        $mock->shouldReceive('constructEvent')
            ->once()
            ->andReturn(Event::constructFrom(json_decode($payload, true)));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, $nonExistentGatewayId));

        $response = $this->postJson('/api/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=fake_signature',
        ]);

        $response->assertStatus(200)
            ->assertJson(['received' => true]);
    }

    public function test_webhook_returns_200_for_unhandled_event_type(): void
    {
        $payload = $this->webhookPayload('pi_any_123', 'charge.refunded');

        $mock = \Mockery::mock('alias:' . Webhook::class);
        $mock->shouldReceive('constructEvent')
            ->once()
            ->andReturn(Event::constructFrom(json_decode($payload, true)));

        $response = $this->postJson('/api/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=fake_signature',
        ]);

        $response->assertStatus(200)
            ->assertJson(['received' => true]);
    }

    public function test_webhook_refunds_and_notifies_when_reservation_is_cancelled(): void
    {
        Notification::fake();

        [$reservation, $payment] = $this->createReservationWithPayment(Reservation::STATUS_CANCELLED);

        $payload = $this->webhookPayload($payment->payment_gateway_id);

        $webhookMock = \Mockery::mock('alias:' . Webhook::class);
        $webhookMock->shouldReceive('constructEvent')
            ->once()
            ->andReturn(Event::constructFrom(json_decode($payload, true)));

        $paymentMock = $this->mock(PaymentService::class);
        $paymentMock->shouldReceive('handleSucceededPayment')->once()->andReturn($payment->fresh());
        $paymentMock->shouldReceive('refund')->once();

        $response = $this->postJson('/api/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=fake_signature',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_CANCELLED,
        ]);

        Notification::assertSentTo(
            $reservation->user,
            ReservationPaymentRefundedNotification::class,
        );
    }

    public function test_webhook_refunds_and_notifies_when_reservation_is_expired(): void
    {
        Notification::fake();

        [$reservation, $payment] = $this->createReservationWithPayment(Reservation::STATUS_EXPIRED);

        $payload = $this->webhookPayload($payment->payment_gateway_id);

        $webhookMock = \Mockery::mock('alias:' . Webhook::class);
        $webhookMock->shouldReceive('constructEvent')
            ->once()
            ->andReturn(Event::constructFrom(json_decode($payload, true)));

        $paymentMock = $this->mock(PaymentService::class);
        $paymentMock->shouldReceive('handleSucceededPayment')->once()->andReturn($payment->fresh());
        $paymentMock->shouldReceive('refund')->once();

        $response = $this->postJson('/api/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=fake_signature',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_EXPIRED,
        ]);

        Notification::assertSentTo(
            $reservation->user,
            ReservationPaymentRefundedNotification::class,
        );
    }

    public function test_webhook_marks_payment_as_failed_on_payment_failed(): void
    {
        [$reservation, $payment] = $this->createReservationWithPayment();

        $payload = $this->webhookPayload(
            $payment->payment_gateway_id,
            'payment_intent.payment_failed',
            ['code' => 'card_declined', 'message' => 'Your card was declined.'],
        );

        $mock = \Mockery::mock('alias:' . Webhook::class);
        $mock->shouldReceive('constructEvent')
            ->once()
            ->andReturn(Event::constructFrom(json_decode($payload, true)));

        $response = $this->postJson('/api/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=fake_signature',
        ]);

        $response->assertStatus(200)->assertJson(['received' => true]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_FAILED,
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_PENDING,
        ]);
    }

    public function test_webhook_does_not_overwrite_succeeded_payment_on_payment_failed(): void
    {
        [$reservation, $payment] = $this->createReservationWithPayment(Reservation::STATUS_CONFIRMED);

        $payment->update([
            'status' => Payment::STATUS_SUCCEEDED,
            'paid_at' => now(),
        ]);

        $payload = $this->webhookPayload(
            $payment->payment_gateway_id,
            'payment_intent.payment_failed',
            ['code' => 'card_declined', 'message' => 'Card declined.'],
        );

        $mock = \Mockery::mock('alias:' . Webhook::class);
        $mock->shouldReceive('constructEvent')
            ->once()
            ->andReturn(Event::constructFrom(json_decode($payload, true)));

        $response = $this->postJson('/api/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=fake_signature',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_SUCCEEDED,
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);
    }

    public function test_webhook_is_idempotent_for_already_failed_payment(): void
    {
        [, $payment] = $this->createReservationWithPayment();

        $payment->update(['status' => Payment::STATUS_FAILED]);

        $payload = $this->webhookPayload(
            $payment->payment_gateway_id,
            'payment_intent.payment_failed',
            ['code' => 'card_declined', 'message' => 'Card declined.'],
        );

        $mock = \Mockery::mock('alias:' . Webhook::class);
        $mock->shouldReceive('constructEvent')
            ->once()
            ->andReturn(Event::constructFrom(json_decode($payload, true)));

        $response = $this->postJson('/api/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=fake_signature',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_FAILED,
        ]);
    }

    public function test_webhook_logs_error_details_on_payment_failed(): void
    {
        [, $payment] = $this->createReservationWithPayment();

        $payload = $this->webhookPayload(
            $payment->payment_gateway_id,
            'payment_intent.payment_failed',
            ['code' => 'card_declined', 'message' => 'Your card was declined.'],
        );

        $mock = \Mockery::mock('alias:' . Webhook::class);
        $mock->shouldReceive('constructEvent')
            ->once()
            ->andReturn(Event::constructFrom(json_decode($payload, true)));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($payment) {
                return $message === 'Stripe payment failed'
                    && $context['payment_id'] === $payment->id
                    && $context['gateway_id'] === $payment->payment_gateway_id
                    && $context['error_code'] === 'card_declined'
                    && $context['error_message'] === 'Your card was declined.';
            });

        $response = $this->postJson('/api/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=fake_signature',
        ]);

        $response->assertStatus(200);
    }

    public function test_webhook_returns_200_when_payment_not_found_on_failed(): void
    {
        $nonExistentGatewayId = 'pi_non_existent_456';
        $payload = $this->webhookPayload(
            $nonExistentGatewayId,
            'payment_intent.payment_failed',
            ['code' => 'card_declined', 'message' => 'Card declined.'],
        );

        $mock = \Mockery::mock('alias:' . Webhook::class);
        $mock->shouldReceive('constructEvent')
            ->once()
            ->andReturn(Event::constructFrom(json_decode($payload, true)));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, $nonExistentGatewayId));

        $response = $this->postJson('/api/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=fake_signature',
        ]);

        $response->assertStatus(200)->assertJson(['received' => true]);
    }
}
