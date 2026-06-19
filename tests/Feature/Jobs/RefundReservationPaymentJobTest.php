<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RefundReservationPaymentJob;
use App\Models\Payment;
use App\Models\Reservation;
use App\Notifications\ReservationCancelledNotification;
use App\Repositories\ReservationRepository;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class RefundReservationPaymentJobTest extends TestCase
{
    use CreatesUsers;
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

    private function runJob(int $reservationId): void
    {
        (new RefundReservationPaymentJob($reservationId))->handle(
            app(ReservationRepository::class),
            $this->paymentServiceMock,
        );
    }

    public function test_refunds_pending_payment_and_notifies_customer(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $reservation = Reservation::factory()->cancelled()->create(['user_id' => $client->id]);
        $payment = Payment::factory()->for($reservation)->create([
            'status' => Payment::STATUS_REFUND_PENDING,
            'refund_amount' => 5.00,
        ]);

        $this->paymentServiceMock
            ->shouldReceive('refund')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $payment->id), 5.00);

        $this->runJob($reservation->id);

        Notification::assertSentToTimes($client, ReservationCancelledNotification::class, 1);
    }

    public function test_is_noop_when_payment_no_longer_refund_pending(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $reservation = Reservation::factory()->cancelled()->create(['user_id' => $client->id]);
        Payment::factory()->for($reservation)->create([
            'status' => Payment::STATUS_REFUNDED,
            'refund_amount' => 5.00,
        ]);

        $this->paymentServiceMock->shouldNotReceive('refund');

        $this->runJob($reservation->id);

        Notification::assertNothingSent();
    }

    public function test_is_noop_when_reservation_not_cancelled(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        Payment::factory()->for($reservation)->create([
            'status' => Payment::STATUS_REFUND_PENDING,
            'refund_amount' => 5.00,
        ]);

        $this->paymentServiceMock->shouldNotReceive('refund');

        $this->runJob($reservation->id);

        Notification::assertNothingSent();
    }

    public function test_failed_marks_payment_refund_failed_logs_and_notifies(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $reservation = Reservation::factory()->cancelled()->create(['user_id' => $client->id]);
        $payment = Payment::factory()->for($reservation)->create([
            'status' => Payment::STATUS_REFUND_PENDING,
            'refund_amount' => 5.00,
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('Refund permanently failed on reservation cancellation', Mockery::subset([
                'reservation_id' => $reservation->id,
                'payment_id' => $payment->id,
            ]));

        (new RefundReservationPaymentJob($reservation->id))->failed(new \Exception('Stripe unavailable'));

        $this->assertSame(Payment::STATUS_REFUND_FAILED, $payment->fresh()->status);
        Notification::assertSentToTimes($client, ReservationCancelledNotification::class, 1);
    }
}
