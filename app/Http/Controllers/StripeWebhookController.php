<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentNotFoundException;
use App\Services\PaymentService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(
        private ReservationService $reservationService,
        private PaymentService $paymentService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Firma invalida.'], 403);
        }

        $intent = $event->data->object;

        try {
            match ($event->type) {
                'payment_intent.succeeded' => $this->reservationService->confirmPayment($intent->id),
                'payment_intent.payment_failed' => $this->paymentService->handleFailedPayment(
                    $intent->id,
                    $intent->last_payment_error?->code,
                    $intent->last_payment_error?->message,
                ),
                default => null,
            };
        } catch (PaymentNotFoundException $e) {
            Log::warning("Stripe webhook: {$e->getMessage()}");
        }

        return response()->json(['received' => true]);
    }
}
