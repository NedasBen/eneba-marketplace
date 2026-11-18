<?php

namespace App\Services\Payments;

/**
 * A card payment provider. The app ships only the simulated one; a real
 * provider (Stripe, Adyen, ...) would implement this behind the same calls.
 */
interface PaymentGateway
{
    /**
     * Provider name stored on each payment row.
     */
    public function name(): string;

    /**
     * Charge the card once. Declines are a normal result, not an exception.
     *
     * @param  string  $orderReference  passed to the provider for reconciliation
     */
    public function charge(int $amountCents, CardDetails $card, string $orderReference): PaymentResult;
}
