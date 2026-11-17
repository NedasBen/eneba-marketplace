<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Succeeded = 'succeeded';
    case Declined = 'declined';
}
