<?php

namespace App\Support;

/**
 * The API's single money shape: integer cents plus currency.
 * The frontend formats it with Intl.NumberFormat.
 */
final class Money
{
    public const CURRENCY = 'EUR';

    /**
     * @return array{amount_cents: int, currency: string}
     */
    public static function fromCents(int $cents): array
    {
        return ['amount_cents' => $cents, 'currency' => self::CURRENCY];
    }

    /**
     * @return array{amount_cents: int, currency: string}|null
     */
    public static function fromNullableCents(?int $cents): ?array
    {
        return $cents === null ? null : self::fromCents($cents);
    }
}
