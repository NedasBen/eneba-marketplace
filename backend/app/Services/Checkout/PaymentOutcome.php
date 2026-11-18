<?php

namespace App\Services\Checkout;

use App\Models\Order;

/**
 * Result of paying an order: paid (now or earlier), declined (order stays
 * pending, retry allowed) or expired (the reservation is gone).
 */
final readonly class PaymentOutcome
{
    public const PAID = 'paid';

    public const DECLINED = 'declined';

    public const EXPIRED = 'expired';

    private function __construct(
        public string $status,
        public Order $order,
        public ?string $failureCode = null,
    ) {}

    public static function paid(Order $order): self
    {
        return new self(self::PAID, $order);
    }

    public static function declined(Order $order, string $failureCode): self
    {
        return new self(self::DECLINED, $order, $failureCode);
    }

    public static function expired(Order $order): self
    {
        return new self(self::EXPIRED, $order);
    }
}
