<?php

namespace App\Enums;

enum ProductKeyStatus: string
{
    case Available = 'available';
    /** Held for a pending order until `reserved_until`. */
    case Reserved = 'reserved';
    case Sold = 'sold';
}
