<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ExpireReservationJob;
use App\Models\Payment;
use App\Models\Reservation;
use App\Notifications\ReservationExpiredNotification;
use App\Repositories\ReservationRepository;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class ExpireReservationJobTest extends TestCase
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

    private function runJob(int $reservationId): void
    {
        (new ExpireReservationJob($reservationId))->handle(
            app(ReservationRepository::class),
            $this->paymentServiceMock,
        );
    }

    public function test_first_run_on_pending_reservation_expires_cancels_payment_and_notifies(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $reservation = Reservation::factory()->pending()->create(['user_id' => $client->id]);
        $payment = Payment::factory()->for($reservation)->create();

        $this->paymentServiceMock
            ->shouldReceive('cancelPaymentIntent')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $payment->id));

        $this->runJob($reservation->id);

        $this->assertSame(Reservation::STATUS_EXPIRED, $reservation->fresh()->status);
        Notification::assertSentToTimes($client, ReservationExpiredNotification::class, 1);
    }

    public function test_second_run_on_expired_reservation_with_pending_payment_cancels_payment_without_notifying(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $reservation = Reservation::factory()->expired()->create(['user_id' => $client->id]);
        $payment = Payment::factory()->for($reservation)->create();

        $this->paymentServiceMock
            ->shouldReceive('cancelPaymentIntent')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $payment->id));

        $this->runJob($reservation->id);

        $this->assertSame(Reservation::STATUS_EXPIRED, $reservation->fresh()->status);
        Notification::assertNotSentTo($client, ReservationExpiredNotification::class);
    }

    public function test_second_run_on_expired_reservation_with_failed_payment_is_noop(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $reservation = Reservation::factory()->expired()->create(['user_id' => $client->id]);
        Payment::factory()->for($reservation)->create(['status' => Payment::STATUS_FAILED]);

        $this->paymentServiceMock->shouldNotReceive('cancelPaymentIntent');

        $this->runJob($reservation->id);

        $this->assertSame(Reservation::STATUS_EXPIRED, $reservation->fresh()->status);
        Notification::assertNotSentTo($client, ReservationExpiredNotification::class);
    }

    public function test_run_on_confirmed_reservation_is_noop(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $reservation = Reservation::factory()->confirmed()->create(['user_id' => $client->id]);
        Payment::factory()->for($reservation)->create(['status' => Payment::STATUS_SUCCEEDED]);

        $this->paymentServiceMock->shouldNotReceive('cancelPaymentIntent');

        $this->runJob($reservation->id);

        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->fresh()->status);
        Notification::assertNotSentTo($client, ReservationExpiredNotification::class);
    }

    public function test_run_on_missing_reservation_is_noop(): void
    {
        Notification::fake();

        $this->paymentServiceMock->shouldNotReceive('cancelPaymentIntent');

        $this->runJob(99999);

        Notification::assertNothingSent();
    }

    public function test_run_on_pending_reservation_without_payment_only_expires_and_notifies(): void
    {
        Notification::fake();

        $client = $this->clientUser();
        $reservation = Reservation::factory()->pending()->create(['user_id' => $client->id]);

        $this->paymentServiceMock->shouldNotReceive('cancelPaymentIntent');

        $this->runJob($reservation->id);

        $this->assertSame(Reservation::STATUS_EXPIRED, $reservation->fresh()->status);
        Notification::assertSentToTimes($client, ReservationExpiredNotification::class, 1);
    }
}
