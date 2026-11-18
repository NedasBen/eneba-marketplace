<?php

namespace App\Services\Payments;

/**
 * What the payment provider answered for one charge attempt.
 */
final readonly class PaymentResult
{
    private function __construct(
        public bool $succeeded,
        public string $providerReference,
        public ?string $failureCode,
    ) {}

    public static function succeeded(string $providerReference): self
    {
        return new self(true, $providerReference, null);
    }

    public static function declined(string $providerReference, string $failureCode): self
    {
        return new self(false, $providerReference, $failureCode);
    }
}
