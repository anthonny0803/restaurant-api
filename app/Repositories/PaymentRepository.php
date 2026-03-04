<?php

namespace App\Repositories;

use App\Models\Payment;

class PaymentRepository
{
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function findByGatewayId(string $gatewayId): ?Payment
    {
        return Payment::where('payment_gateway_id', $gatewayId)->first();
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);

        return $payment;
    }
}
