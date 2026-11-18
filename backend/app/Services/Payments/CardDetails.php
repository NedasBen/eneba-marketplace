<?php

namespace App\Services\Payments;

/**
 * Card data for one charge. Lives only for the request: never stored,
 * logged or serialised; payments keep the last four digits only.
 */
final readonly class CardDetails
{
    public function __construct(
        public string $number,
        public int $expiryMonth,
        public int $expiryYear,
        #[\SensitiveParameter] public string $cvc,
        public string $holderName,
    ) {}

    public function lastFour(): string
    {
        return substr($this->number, -4);
    }

    public function __debugInfo(): array
    {
        return ['number' => '**** '.$this->lastFour()];
    }
}
