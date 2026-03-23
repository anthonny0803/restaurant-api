<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentNotFoundException extends RuntimeException
{
    public function __construct(string $gatewayId)
    {
        parent::__construct("Payment not found for gateway ID: {$gatewayId}");
    }
}
