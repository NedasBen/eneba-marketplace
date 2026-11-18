<?php

namespace App\Services\Payments;

/**
 * The demo's test cards (docs/ARCHITECTURE.md "Simulated test cards").
 * Only these numbers are accepted, so nobody types a real card into a demo.
 */
final class TestCards
{
    public const SUCCESS = '4242424242424242';

    public const CARD_DECLINED = '4000000000000002';

    public const INSUFFICIENT_FUNDS = '4000000000009995';

    /**
     * Card number => failure code (null = the charge succeeds).
     *
     * @var array<string, string|null>
     */
    public const OUTCOMES = [
        self::SUCCESS => null,
        self::CARD_DECLINED => 'card_declined',
        self::INSUFFICIENT_FUNDS => 'insufficient_funds',
    ];

    public static function isTestCard(string $number): bool
    {
        return array_key_exists($number, self::OUTCOMES);
    }

    /**
     * Luhn checksum, as card networks use to catch typos.
     */
    public static function passesLuhn(string $number): bool
    {
        if (preg_match('/^\d{12,19}$/', $number) !== 1) {
            return false;
        }

        $sum = 0;
        $double = false;

        for ($index = strlen($number) - 1; $index >= 0; $index--) {
            $digit = (int) $number[$index];

            if ($double) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}
