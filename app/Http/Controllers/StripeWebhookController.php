<?php

namespace App\Http\Controllers;

use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(private ReservationService $reservationService) {}

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

        if ($event->type === 'payment_intent.succeeded') {
            $gatewayId = $event->data->object->id;
            $this->reservationService->confirmPayment($gatewayId);
        }

        return response()->json(['received' => true]);
    }
}
