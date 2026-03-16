<?php

namespace Tests\Feature;

use App\Jobs\ExpireReservationJob;
use App\Jobs\SendReservationRemindersJob;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use App\Notifications\ReservationCancelledNotification;
use App\Notifications\ReservationConfirmedNotification;
use App\Notifications\ReservationExpiredNotification;
use App\Notifications\ReservationReminderNotification;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Mockery\MockInterface;
use Stripe\Event;
use Stripe\Webhook;
use Tests\TestCase;

class ReservationNotificationTest extends TestCase
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

    // ── Confirmation ──────────────────────────────────────────

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

    public function test_confirmed_notification_is_sent_on_payment_success(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $table = $this->createTable();
        $reservation = $this->createReservation($client, $table, [
            'status' => Reservation::STATUS_PENDING,
        ]);

        $payment = $reservation->payment()->create([
            'amount' => 10.00,
            'status' => Payment::STATUS_PENDING,
            'payment_gateway_id' => 'pi_test_confirm',
        ]);

        $this->paymentServiceMock
            ->shouldReceive('handleSucceededPayment')
            ->with('pi_test_confirm')
            ->once()
            ->andReturn($payment->fresh());

        $payload = $this->webhookPayload('pi_test_confirm');

        $webhookMock = Mockery::mock('alias:' . Webhook::class);
        $webhookMock->shouldReceive('constructEvent')
            ->once()
            ->andReturn(Event::constructFrom(json_decode($payload, true)));

        $this->postJson('/api/stripe/webhook', [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=fake_signature',
        ]);

        Notification::assertSentTo($client, ReservationConfirmedNotification::class);
    }

    // ── Cancellation ──────────────────────────────────────────

    public function test_cancelled_notification_is_sent_with_refund_amount(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $table = $this->createTable();
        $reservation = $this->createReservation($client, $table);

        $reservation->payment()->create([
            'amount' => 10.00,
            'status' => Payment::STATUS_SUCCEEDED,
            'payment_gateway_id' => 'pi_test_cancel',
            'paid_at' => now(),
        ]);

        $this->paymentServiceMock
            ->shouldReceive('refund')
            ->once();

        $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/cancel");

        Notification::assertSentTo($client, ReservationCancelledNotification::class);
    }

    public function test_cancelled_notification_is_sent_without_refund_when_no_payment(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $table = $this->createTable();
        $reservation = $this->createReservation($client, $table, [
            'status' => Reservation::STATUS_PENDING,
        ]);

        $this->paymentServiceMock->shouldNotReceive('refund');

        $this->actingAs($client)
            ->postJson("/api/reservations/{$reservation->id}/cancel");

        Notification::assertSentTo($client, ReservationCancelledNotification::class);
    }

    // ── Expiration ────────────────────────────────────────────

    public function test_expired_notification_is_sent_when_reservation_expires(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $table = $this->createTable();
        $reservation = $this->createReservation($client, $table, [
            'status' => Reservation::STATUS_PENDING,
        ]);

        $job = new ExpireReservationJob($reservation->id);
        $job->handle(app(\App\Repositories\ReservationRepository::class), $this->paymentServiceMock);

        Notification::assertSentTo($client, ReservationExpiredNotification::class);
    }

    public function test_expired_notification_is_sent_for_guest_reservation(): void
    {
        Notification::fake();

        $guestUser = User::create([
            'name' => 'Guest User',
            'email' => 'guest@example.com',
        ]);
        $guestUser->assignRole('client');

        $table = $this->createTable();
        $reservation = $this->createReservation($guestUser, $table, [
            'status' => Reservation::STATUS_PENDING,
        ]);

        $job = new ExpireReservationJob($reservation->id);
        $job->handle(app(\App\Repositories\ReservationRepository::class), $this->paymentServiceMock);

        Notification::assertSentTo($guestUser, ReservationExpiredNotification::class);
    }

    // ── Reminder ──────────────────────────────────────────────

    public function test_reminder_is_sent_for_eligible_reservations(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $table = $this->createTable();

        $reservation = $this->createReservation($client, $table, [
            'date' => now()->addHours(12)->format('Y-m-d'),
            'start_time' => now()->addHours(12)->format('H:i:s'),
        ]);

        $reservation->forceFill(['created_at' => now()->subDays(2)])->save();

        $job = new SendReservationRemindersJob();
        $job->handle(
            app(\App\Repositories\RestaurantSettingRepository::class),
            app(\App\Repositories\ReservationRepository::class),
        );

        Notification::assertSentTo($client, ReservationReminderNotification::class);
    }

    public function test_reminder_is_not_sent_for_same_day_reservations(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $table = $this->createTable();

        $this->createReservation($client, $table, [
            'date' => now()->addHours(12)->format('Y-m-d'),
            'start_time' => now()->addHours(12)->format('H:i:s'),
            'created_at' => now(),
        ]);

        $job = new SendReservationRemindersJob();
        $job->handle(
            app(\App\Repositories\RestaurantSettingRepository::class),
            app(\App\Repositories\ReservationRepository::class),
        );

        Notification::assertNothingSent();
    }

    public function test_reminder_is_not_sent_twice(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $table = $this->createTable();

        $this->createReservation($client, $table, [
            'date' => now()->addHours(12)->format('Y-m-d'),
            'start_time' => now()->addHours(12)->format('H:i:s'),
            'created_at' => now()->subDays(2),
            'reminder_sent_at' => now()->subHour(),
        ]);

        $job = new SendReservationRemindersJob();
        $job->handle(
            app(\App\Repositories\RestaurantSettingRepository::class),
            app(\App\Repositories\ReservationRepository::class),
        );

        Notification::assertNothingSent();
    }
}
