<?php

namespace App\Services;

/**
 * A payment as the gateway's own server confirmed it: never built from what the
 * browser or the app sent. `$reference` is the gateway's id for the payment
 * (or one this store generated and the gateway echoed back), which
 * GatewayPaymentService accepts only once.
 */
final readonly class VerifiedPayment
{
    public function __construct(
        public string $gateway,
        public string $reference,
        public float $amount,
        public ?string $details = null,
    ) {}
}
