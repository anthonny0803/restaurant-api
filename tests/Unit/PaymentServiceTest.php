<?php

namespace Tests\Unit;

use App\Exceptions\PaymentNotFoundException;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    private PaymentService $service;
    private PaymentRepository&MockInterface $paymentRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = Mockery::mock(PaymentRepository::class);
        $this->service = new PaymentService($this->paymentRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── createPaymentIntent ──────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_create_payment_intent_passes_idempotency_key_derived_from_reservation_id(): void
    {
        $reservationId = 42;
        $expectedKey = "reservation_{$reservationId}_payment_intent";

        $intent = (object) [
            'id' => 'pi_idempotent_test',
            'client_secret' => 'secret_xyz',
        ];

        $stripeMock = Mockery::mock('alias:Stripe\PaymentIntent');
        $stripeMock
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $params, array $options) use ($expectedKey, $reservationId) {
                return $options['idempotency_key'] === $expectedKey
                    && $params['amount'] === 2550
                    && $params['metadata']['reservation_id'] === $reservationId;
            })
            ->andReturn($intent);

        $payment = Mockery::mock(Payment::class);
        $this->paymentRepository
            ->shouldReceive('create')
            ->once()
            ->with([
                'reservation_id' => $reservationId,
                'amount' => 25.50,
                'payment_gateway_id' => 'pi_idempotent_test',
            ])
            ->andReturn($payment);

        $result = $this->service->createPaymentIntent($reservationId, 25.50);

        $this->assertSame($payment, $result['payment']);
        $this->assertSame('secret_xyz', $result['client_secret']);
    }

    // ── handleSucceededPayment ───────────────────────────────────

    public function test_handle_succeeded_payment_throws_when_payment_not_found(): void
    {
        $this->paymentRepository
            ->shouldReceive('findByGatewayId')
            ->with('pi_missing')
            ->once()
            ->andReturn(null);

        $this->expectException(PaymentNotFoundException::class);

        $this->service->handleSucceededPayment('pi_missing');
    }

    public function test_handle_succeeded_payment_is_no_op_when_already_succeeded(): void
    {
        $payment = $this->makePaymentMock(Payment::STATUS_SUCCEEDED);

        $this->paymentRepository
            ->shouldReceive('findByGatewayId')
            ->with('pi_succeeded')
            ->once()
            ->andReturn($payment);

        $this->paymentRepository->shouldNotReceive('update');

        $result = $this->service->handleSucceededPayment('pi_succeeded');

        $this->assertSame($payment, $result);
    }

    public function test_handle_succeeded_payment_updates_status_and_paid_at_when_pending(): void
    {
        $payment = $this->makePaymentMock(Payment::STATUS_PENDING);

        $this->paymentRepository
            ->shouldReceive('findByGatewayId')
            ->with('pi_pending')
            ->once()
            ->andReturn($payment);

        $this->paymentRepository
            ->shouldReceive('update')
            ->once()
            ->withArgs(function (Payment $target, array $attributes) use ($payment) {
                return $target === $payment
                    && $attributes['status'] === Payment::STATUS_SUCCEEDED
                    && $attributes['paid_at'] !== null;
            });

        $result = $this->service->handleSucceededPayment('pi_pending');

        $this->assertSame($payment, $result);
    }

    // ── handleFailedPayment ──────────────────────────────────────

    public function test_handle_failed_payment_throws_when_payment_not_found(): void
    {
        $this->paymentRepository
            ->shouldReceive('findByGatewayId')
            ->with('pi_missing')
            ->once()
            ->andReturn(null);

        $this->expectException(PaymentNotFoundException::class);

        $this->service->handleFailedPayment('pi_missing');
    }

    public function test_handle_failed_payment_updates_status_to_failed_when_pending(): void
    {
        $payment = $this->makePaymentMock(Payment::STATUS_PENDING);

        $this->paymentRepository
            ->shouldReceive('findByGatewayId')
            ->with('pi_pending_1')
            ->once()
            ->andReturn($payment);

        $this->paymentRepository
            ->shouldReceive('update')
            ->with($payment, ['status' => Payment::STATUS_FAILED])
            ->once();

        Log::shouldReceive('warning')->once();

        $result = $this->service->handleFailedPayment('pi_pending_1');

        $this->assertSame($payment, $result);
    }

    public function test_handle_failed_payment_logs_error_details_with_stripe_payload(): void
    {
        $payment = $this->makePaymentMock(
            status: Payment::STATUS_PENDING,
            paymentId: 42,
            gatewayId: 'pi_pending_2',
        );

        $this->paymentRepository
            ->shouldReceive('findByGatewayId')
            ->with('pi_pending_2')
            ->once()
            ->andReturn($payment);

        $this->paymentRepository
            ->shouldReceive('update')
            ->once();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'Stripe payment failed'
                    && $context['payment_id'] === 42
                    && $context['gateway_id'] === 'pi_pending_2'
                    && $context['error_code'] === 'card_declined'
                    && $context['error_message'] === 'Your card was declined.';
            });

        $result = $this->service->handleFailedPayment(
            'pi_pending_2',
            'card_declined',
            'Your card was declined.',
        );

        $this->assertSame($payment, $result);
    }

    public function test_handle_failed_payment_logs_with_null_error_fields_when_stripe_omits_them(): void
    {
        $payment = $this->makePaymentMock(Payment::STATUS_PENDING);

        $this->paymentRepository
            ->shouldReceive('findByGatewayId')
            ->once()
            ->andReturn($payment);

        $this->paymentRepository
            ->shouldReceive('update')
            ->once();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) => $context['error_code'] === null
                && $context['error_message'] === null);

        $result = $this->service->handleFailedPayment('pi_no_error');

        $this->assertSame($payment, $result);
    }

    public function test_handle_failed_payment_is_no_op_when_payment_already_succeeded(): void
    {
        $payment = $this->makePaymentMock(Payment::STATUS_SUCCEEDED);

        $this->paymentRepository
            ->shouldReceive('findByGatewayId')
            ->with('pi_succeeded')
            ->once()
            ->andReturn($payment);

        $this->paymentRepository->shouldNotReceive('update');

        $result = $this->service->handleFailedPayment('pi_succeeded');

        $this->assertSame($payment, $result);
    }

    public function test_handle_failed_payment_is_no_op_when_payment_already_failed(): void
    {
        $payment = $this->makePaymentMock(Payment::STATUS_FAILED);

        $this->paymentRepository
            ->shouldReceive('findByGatewayId')
            ->with('pi_failed')
            ->once()
            ->andReturn($payment);

        $this->paymentRepository->shouldNotReceive('update');

        $result = $this->service->handleFailedPayment('pi_failed');

        $this->assertSame($payment, $result);
    }

    public function test_handle_failed_payment_is_no_op_when_payment_refunded(): void
    {
        $payment = $this->makePaymentMock(Payment::STATUS_REFUNDED);

        $this->paymentRepository
            ->shouldReceive('findByGatewayId')
            ->with('pi_refunded')
            ->once()
            ->andReturn($payment);

        $this->paymentRepository->shouldNotReceive('update');

        $result = $this->service->handleFailedPayment('pi_refunded');

        $this->assertSame($payment, $result);
    }

    // ── cancelPaymentIntent ──────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_cancel_payment_intent_cancels_stripe_intent_and_marks_failed(): void
    {
        $payment = $this->makePaymentMock(Payment::STATUS_PENDING, gatewayId: 'pi_to_cancel');

        $intent = Mockery::mock();
        $intent->shouldReceive('cancel')->once();

        $stripeMock = Mockery::mock('alias:Stripe\PaymentIntent');
        $stripeMock
            ->shouldReceive('retrieve')
            ->with('pi_to_cancel')
            ->once()
            ->andReturn($intent);

        $this->paymentRepository
            ->shouldReceive('update')
            ->with($payment, ['status' => Payment::STATUS_FAILED])
            ->once();

        $result = $this->service->cancelPaymentIntent($payment);

        $this->assertSame($payment, $result);
    }

    // ── refund ───────────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_refund_full_amount_marks_payment_refunded(): void
    {
        $payment = $this->makePaymentMock(
            Payment::STATUS_SUCCEEDED,
            gatewayId: 'pi_full_refund',
            amount: 25.50,
        );

        $refundMock = Mockery::mock('alias:Stripe\Refund');
        $refundMock->shouldReceive('create')->once();

        $this->paymentRepository
            ->shouldReceive('update')
            ->with($payment, ['status' => Payment::STATUS_REFUNDED, 'refund_amount' => 25.50])
            ->once();

        $result = $this->service->refund($payment, 25.50);

        $this->assertSame($payment, $result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_refund_partial_amount_marks_payment_partially_refunded(): void
    {
        $payment = $this->makePaymentMock(
            Payment::STATUS_SUCCEEDED,
            gatewayId: 'pi_partial_refund',
            amount: 25.50,
        );

        $refundMock = Mockery::mock('alias:Stripe\Refund');
        $refundMock->shouldReceive('create')->once();

        $this->paymentRepository
            ->shouldReceive('update')
            ->with($payment, ['status' => Payment::STATUS_PARTIALLY_REFUNDED, 'refund_amount' => 10.00])
            ->once();

        $result = $this->service->refund($payment, 10.00);

        $this->assertSame($payment, $result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_refund_converts_amount_to_cents_for_stripe(): void
    {
        $payment = $this->makePaymentMock(
            Payment::STATUS_SUCCEEDED,
            gatewayId: 'pi_cents',
            amount: 25.50,
        );

        $refundMock = Mockery::mock('alias:Stripe\Refund');
        $refundMock
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $params) {
                return $params['payment_intent'] === 'pi_cents'
                    && $params['amount'] === 1099;
            });

        $this->paymentRepository->shouldReceive('update')->once();

        $result = $this->service->refund($payment, 10.99);

        $this->assertSame($payment, $result);
    }

    private function makePaymentMock(
        string $status,
        int $paymentId = 1,
        string $gatewayId = 'pi_test',
        float $amount = 25.50,
    ): Payment&MockInterface {
        /** @var Payment&MockInterface $payment */
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->shouldReceive('getAttribute')->with('status')->andReturn($status);
        $payment->shouldReceive('getAttribute')->with('id')->andReturn($paymentId);
        $payment->shouldReceive('getAttribute')->with('payment_gateway_id')->andReturn($gatewayId);
        $payment->shouldReceive('getAttribute')->with('amount')->andReturn($amount);
        $payment->shouldReceive('fresh')->andReturnSelf();

        return $payment;
    }
}
