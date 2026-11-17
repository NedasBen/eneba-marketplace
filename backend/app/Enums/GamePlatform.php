<?php

namespace App\Enums;

/**
 * Platform families a game is really released on. Each maps to the store
 * where its digital key is activated, which is how Eneba labels products.
 */
enum GamePlatform: string
{
    case Pc = 'pc';
    case PlayStation = 'playstation';
    case Xbox = 'xbox';
    case NintendoSwitch = 'nintendo-switch';

    public function label(): string
    {
        return match ($this) {
            self::Pc => 'PC',
            self::PlayStation => 'PlayStation',
            self::Xbox => 'Xbox',
            self::NintendoSwitch => 'Nintendo Switch',
        };
    }

    public function activationService(): string
    {
        return match ($this) {
            self::Pc => 'Steam',
            self::PlayStation => 'PSN',
            self::Xbox => 'Xbox Live',
            self::NintendoSwitch => 'Nintendo eShop',
        };
    }
}
