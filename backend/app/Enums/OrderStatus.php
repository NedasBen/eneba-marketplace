<?php

namespace App\Enums;

enum OrderStatus: string
{
    /** Keys are reserved until `expires_at` while the buyer pays. */
    case Pending = 'pending';
    case Paid = 'paid';
    /** The reservation ran out before payment; keys went back to stock. */
    case Expired = 'expired';
}
