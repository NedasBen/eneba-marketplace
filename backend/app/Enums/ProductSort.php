<?php

namespace App\Enums;

/**
 * Store sort orders. Labels are served by the filters endpoint so the
 * frontend never hardcodes them.
 */
enum ProductSort: string
{
    case Popularity = 'popularity';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case ReleaseDate = 'release_date';
    case Name = 'name';

    public function label(): string
    {
        return match ($this) {
            self::Popularity => 'Best selling',
            self::PriceAsc => 'Price: low to high',
            self::PriceDesc => 'Price: high to low',
            self::ReleaseDate => 'Newest releases',
            self::Name => 'Name: A to Z',
        };
    }
}
