<?php

namespace App\Enums;

/**
 * Store sort orders. Labels are served by the filters endpoint so the
 * frontend never hardcodes them.
 */
enum ProductSort: string
{
    /** Search ranking; only meaningful with a query (otherwise falls back to popularity). */
    case Relevance = 'relevance';
    case Popularity = 'popularity';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case ReleaseDate = 'release_date';
    case Name = 'name';

    public function requiresQuery(): bool
    {
        return $this === self::Relevance;
    }

    public function label(): string
    {
        return match ($this) {
            self::Relevance => 'Relevance',
            self::Popularity => 'Best selling',
            self::PriceAsc => 'Price: low to high',
            self::PriceDesc => 'Price: high to low',
            self::ReleaseDate => 'Newest releases',
            self::Name => 'Name: A to Z',
        };
    }
}
