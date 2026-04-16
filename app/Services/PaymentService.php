<?php

namespace App\Services;

use App\Exceptions\PaymentNotFoundException;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use Stripe\PaymentIntent;
use Stripe\Refund;

class PaymentService
{
    public function __construct(
        private PaymentRepository $paymentRepository,
    ) {}

    public function createPaymentIntent(int $reservationId, float $amount): array
    {
        $amountInCents = (int) round($amount * 100);

        $intent = PaymentIntent::create([
            'amount' => $amountInCents,
            'currency' => 'eur',
            'payment_method_types' => ['card'],
            'metadata' => [
                'reservation_id' => $reservationId,
            ],
        ]);

        $payment = $this->paymentRepository->create([
            'reservation_id' => $reservationId,
            'amount' => $amount,
            'payment_gateway_id' => $intent->id,
        ]);

        return [
            'payment' => $payment,
            'client_secret' => $intent->client_secret,
        ];
    }

    public function handleSucceededPayment(string $gatewayId): Payment
    {
        $payment = $this->paymentRepository->findByGatewayId($gatewayId);

        if (! $payment) {
            throw new PaymentNotFoundException($gatewayId);
        }

        if ($payment->status === Payment::STATUS_SUCCEEDED) {
            return $payment;
        }

        $this->paymentRepository->update($payment, [
            'status' => Payment::STATUS_SUCCEEDED,
            'paid_at' => now(),
        ]);

        return $payment->fresh();
    }

    public function cancelPaymentIntent(Payment $payment): Payment
    {
        PaymentIntent::retrieve($payment->payment_gateway_id)->cancel();

        $this->paymentRepository->update($payment, [
            'status' => Payment::STATUS_FAILED,
        ]);

        return $payment->fresh();
    }

    public function refund(Payment $payment, float $amount): Payment
    {
        $amountInCents = (int) round($amount * 100);

        Refund::create([
            'payment_intent' => $payment->payment_gateway_id,
            'amount' => $amountInCents,
        ]);

        $status = $amount >= (float) $payment->amount
            ? Payment::STATUS_REFUNDED
            : Payment::STATUS_PARTIALLY_REFUNDED;

        $this->paymentRepository->update($payment, [
            'status' => $status,
            'refund_amount' => $amount,
        ]);

        return $payment->fresh();
    }
}
