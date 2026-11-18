<?php

namespace App\Services\Payments;

use Illuminate\Support\Str;

/**
 * Pretend provider: the outcome depends only on which test card is used.
 * No money moves and nothing leaves the server.
 */
final class SimulatedPaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'simulated';
    }

    public function charge(int $amountCents, CardDetails $card, string $orderReference): PaymentResult
    {
        $reference = 'sim_'.Str::lower((string) Str::ulid());
        // The request only lets test cards through; anything else is declined.
        $failureCode = TestCards::isTestCard($card->number) ? TestCards::OUTCOMES[$card->number] : 'card_declined';

        return $failureCode === null
            ? PaymentResult::succeeded($reference)
            : PaymentResult::declined($reference, $failureCode);
    }
}
