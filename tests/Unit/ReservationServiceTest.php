<?php

namespace Tests\Unit;

use App\Models\CancellationPolicySnapshot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Repositories\ReservationRepository;
use App\Repositories\RestaurantSettingRepository;
use App\Repositories\TableRepository;
use App\Services\PaymentService;
use App\Services\ReservationService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ReservationServiceTest extends TestCase
{
    private ReservationService $service;
    private ReservationRepository&MockInterface $reservationRepository;
    private RestaurantSettingRepository&MockInterface $settingRepository;
    private TableRepository&MockInterface $tableRepository;
    private PaymentService&MockInterface $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reservationRepository = Mockery::mock(ReservationRepository::class);
        $this->settingRepository = Mockery::mock(RestaurantSettingRepository::class);
        $this->tableRepository = Mockery::mock(TableRepository::class);
        $this->paymentService = Mockery::mock(PaymentService::class);

        $this->service = new ReservationService(
            $this->reservationRepository,
            $this->settingRepository,
            $this->tableRepository,
            $this->paymentService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── confirmPayment ───────────────────────────────────────

    public function test_confirm_payment_changes_reservation_to_confirmed(): void
    {
        /** @var Reservation&MockInterface $reservation */
        $reservation = Mockery::mock(Reservation::class)->makePartial();
        $reservation->status = Reservation::STATUS_PENDING;

        /** @var Payment&MockInterface $payment */
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->status = Payment::STATUS_SUCCEEDED;
        $payment->shouldReceive('getAttribute')
            ->with('reservation')
            ->andReturn($reservation);

        $this->paymentService
            ->shouldReceive('handleSucceededPayment')
            ->with('pi_test_123')
            ->once()
            ->andReturn($payment);

        $this->reservationRepository
            ->shouldReceive('updateStatus')
            ->with($reservation, Reservation::STATUS_CONFIRMED)
            ->once();

        /** @var Reservation&MockInterface $freshReservation */
        $freshReservation = Mockery::mock(Reservation::class)->makePartial();
        $freshReservation->status = Reservation::STATUS_CONFIRMED;
        $reservation->shouldReceive('fresh')->once()->andReturn($freshReservation);

        $result = $this->service->confirmPayment('pi_test_123');

        $this->assertEquals(Reservation::STATUS_CONFIRMED, $result->status);
    }

    public function test_confirm_payment_is_idempotent_when_already_confirmed(): void
    {
        /** @var Reservation&MockInterface $reservation */
        $reservation = Mockery::mock(Reservation::class)->makePartial();
        $reservation->status = Reservation::STATUS_CONFIRMED;

        /** @var Payment&MockInterface $payment */
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->shouldReceive('getAttribute')
            ->with('reservation')
            ->andReturn($reservation);

        $this->paymentService
            ->shouldReceive('handleSucceededPayment')
            ->with('pi_test_123')
            ->once()
            ->andReturn($payment);

        $this->reservationRepository->shouldNotReceive('updateStatus');
        $this->paymentService->shouldNotReceive('refund');

        $result = $this->service->confirmPayment('pi_test_123');

        $this->assertEquals(Reservation::STATUS_CONFIRMED, $result->status);
    }

    public function test_confirm_payment_refunds_when_reservation_expired(): void
    {
        /** @var Reservation&MockInterface $reservation */
        $reservation = Mockery::mock(Reservation::class)->makePartial();
        $reservation->status = Reservation::STATUS_EXPIRED;

        /** @var Payment&MockInterface $payment */
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->amount = '10.00';
        $payment->shouldReceive('getAttribute')
            ->with('reservation')
            ->andReturn($reservation);

        $this->paymentService
            ->shouldReceive('handleSucceededPayment')
            ->with('pi_test_123')
            ->once()
            ->andReturn($payment);

        $this->paymentService
            ->shouldReceive('refund')
            ->with($payment, 10.00)
            ->once();

        $this->reservationRepository->shouldNotReceive('updateStatus');

        $result = $this->service->confirmPayment('pi_test_123');

        $this->assertEquals(Reservation::STATUS_EXPIRED, $result->status);
    }

    // ── cancel ───────────────────────────────────────────────

    public function test_cancel_with_full_refund_when_within_deadline(): void
    {
        /** @var Reservation&MockInterface $reservation */
        $reservation = Mockery::mock(Reservation::class)->makePartial();
        $reservation->status = Reservation::STATUS_CONFIRMED;
        $reservation->date = new \DateTime(now()->addDays(3)->format('Y-m-d'));
        $reservation->start_time = '20:00:00';

        /** @var CancellationPolicySnapshot&MockInterface $snapshot */
        $snapshot = Mockery::mock(CancellationPolicySnapshot::class)->makePartial();
        $snapshot->cancellation_deadline_hours = 24;
        $snapshot->refund_percentage = 50;

        /** @var Payment&MockInterface $payment */
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->status = Payment::STATUS_SUCCEEDED;
        $payment->amount = '10.00';

        $reservation->shouldReceive('getAttribute')->with('payment')->andReturn($payment);
        $reservation->shouldReceive('getAttribute')->with('cancellationPolicySnapshot')->andReturn($snapshot);

        $this->reservationRepository
            ->shouldReceive('updateStatus')
            ->with($reservation, Reservation::STATUS_CANCELLED)
            ->once();

        $this->paymentService
            ->shouldReceive('refund')
            ->with($payment, 10.00)
            ->once();

        /** @var Reservation&MockInterface $freshReservation */
        $freshReservation = Mockery::mock(Reservation::class)->makePartial();
        $freshReservation->status = Reservation::STATUS_CANCELLED;
        $reservation->shouldReceive('fresh')->once()->andReturn($freshReservation);

        $result = $this->service->cancel($reservation);

        $this->assertEquals(Reservation::STATUS_CANCELLED, $result->status);
    }

    public function test_cancel_with_partial_refund_when_outside_deadline(): void
    {
        /** @var Reservation&MockInterface $reservation */
        $reservation = Mockery::mock(Reservation::class)->makePartial();
        $reservation->status = Reservation::STATUS_CONFIRMED;
        $reservation->date = new \DateTime(now()->addHours(5)->format('Y-m-d'));
        $reservation->start_time = now()->addHours(5)->format('H:i:s');

        /** @var CancellationPolicySnapshot&MockInterface $snapshot */
        $snapshot = Mockery::mock(CancellationPolicySnapshot::class)->makePartial();
        $snapshot->cancellation_deadline_hours = 24;
        $snapshot->refund_percentage = 50;

        /** @var Payment&MockInterface $payment */
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->status = Payment::STATUS_SUCCEEDED;
        $payment->amount = '10.00';

        $reservation->shouldReceive('getAttribute')->with('payment')->andReturn($payment);
        $reservation->shouldReceive('getAttribute')->with('cancellationPolicySnapshot')->andReturn($snapshot);

        $this->reservationRepository
            ->shouldReceive('updateStatus')
            ->with($reservation, Reservation::STATUS_CANCELLED)
            ->once();

        $this->paymentService
            ->shouldReceive('refund')
            ->with($payment, 5.00)
            ->once();

        /** @var Reservation&MockInterface $freshReservation */
        $freshReservation = Mockery::mock(Reservation::class)->makePartial();
        $freshReservation->status = Reservation::STATUS_CANCELLED;
        $reservation->shouldReceive('fresh')->once()->andReturn($freshReservation);

        $result = $this->service->cancel($reservation);

        $this->assertEquals(Reservation::STATUS_CANCELLED, $result->status);
    }

    public function test_cancel_pending_reservation_cancels_payment_intent(): void
    {
        /** @var Reservation&MockInterface $reservation */
        $reservation = Mockery::mock(Reservation::class)->makePartial();
        $reservation->status = Reservation::STATUS_PENDING;
        $reservation->date = new \DateTime(now()->addDays(3)->format('Y-m-d'));
        $reservation->start_time = '20:00:00';

        /** @var Payment&MockInterface $payment */
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->status = Payment::STATUS_PENDING;

        $reservation->shouldReceive('getAttribute')->with('payment')->andReturn($payment);

        $this->reservationRepository
            ->shouldReceive('updateStatus')
            ->with($reservation, Reservation::STATUS_CANCELLED)
            ->once();

        $this->paymentService
            ->shouldReceive('cancelPaymentIntent')
            ->with($payment)
            ->once();

        $this->paymentService->shouldNotReceive('refund');

        /** @var Reservation&MockInterface $freshReservation */
        $freshReservation = Mockery::mock(Reservation::class)->makePartial();
        $freshReservation->status = Reservation::STATUS_CANCELLED;
        $reservation->shouldReceive('fresh')->once()->andReturn($freshReservation);

        $result = $this->service->cancel($reservation);

        $this->assertEquals(Reservation::STATUS_CANCELLED, $result->status);
    }

    public function test_cancel_rejects_non_cancellable_status(): void
    {
        /** @var Reservation&MockInterface $reservation */
        $reservation = Mockery::mock(Reservation::class)->makePartial();
        $reservation->status = Reservation::STATUS_EXPIRED;

        $this->expectException(ValidationException::class);

        $this->service->cancel($reservation);
    }

    // ── markAsNoShow ─────────────────────────────────────────

    public function test_mark_as_no_show_changes_completed_to_no_show(): void
    {
        /** @var Reservation&MockInterface $reservation */
        $reservation = Mockery::mock(Reservation::class)->makePartial();
        $reservation->status = Reservation::STATUS_COMPLETED;

        $this->reservationRepository
            ->shouldReceive('updateStatus')
            ->with($reservation, Reservation::STATUS_NO_SHOW)
            ->once();

        /** @var Reservation&MockInterface $freshReservation */
        $freshReservation = Mockery::mock(Reservation::class)->makePartial();
        $freshReservation->status = Reservation::STATUS_NO_SHOW;
        $reservation->shouldReceive('fresh')->once()->andReturn($freshReservation);

        $result = $this->service->markAsNoShow($reservation);

        $this->assertEquals(Reservation::STATUS_NO_SHOW, $result->status);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonCompletedStatusProvider')]
    public function test_mark_as_no_show_rejects_non_completed_status(string $status): void
    {
        /** @var Reservation&MockInterface $reservation */
        $reservation = Mockery::mock(Reservation::class)->makePartial();
        $reservation->status = $status;

        $this->expectException(ValidationException::class);

        $this->service->markAsNoShow($reservation);
    }

    public static function nonCompletedStatusProvider(): array
    {
        return [
            'pending' => [Reservation::STATUS_PENDING],
            'confirmed' => [Reservation::STATUS_CONFIRMED],
            'cancelled' => [Reservation::STATUS_CANCELLED],
            'expired' => [Reservation::STATUS_EXPIRED],
            'no_show' => [Reservation::STATUS_NO_SHOW],
        ];
    }

    // ── cancel (same day) ───────────────────────────────────

    public function test_cancel_same_day_applies_partial_refund(): void
    {
        /** @var Reservation&MockInterface $reservation */
        $reservation = Mockery::mock(Reservation::class)->makePartial();
        $reservation->status = Reservation::STATUS_CONFIRMED;
        $reservation->date = new \DateTime(now()->format('Y-m-d'));
        $reservation->start_time = now()->addHours(2)->format('H:i:s');

        /** @var CancellationPolicySnapshot&MockInterface $snapshot */
        $snapshot = Mockery::mock(CancellationPolicySnapshot::class)->makePartial();
        $snapshot->cancellation_deadline_hours = 24;
        $snapshot->refund_percentage = 50;

        /** @var Payment&MockInterface $payment */
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->status = Payment::STATUS_SUCCEEDED;
        $payment->amount = '10.00';

        $reservation->shouldReceive('getAttribute')->with('payment')->andReturn($payment);
        $reservation->shouldReceive('getAttribute')->with('cancellationPolicySnapshot')->andReturn($snapshot);

        $this->reservationRepository
            ->shouldReceive('updateStatus')
            ->with($reservation, Reservation::STATUS_CANCELLED)
            ->once();

        $this->paymentService
            ->shouldReceive('refund')
            ->with($payment, 5.00)
            ->once();

        /** @var Reservation&MockInterface $freshReservation */
        $freshReservation = Mockery::mock(Reservation::class)->makePartial();
        $freshReservation->status = Reservation::STATUS_CANCELLED;
        $reservation->shouldReceive('fresh')->once()->andReturn($freshReservation);

        $result = $this->service->cancel($reservation);

        $this->assertEquals(Reservation::STATUS_CANCELLED, $result->status);
    }
}
