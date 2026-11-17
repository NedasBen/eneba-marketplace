<?php

namespace App\Enums;

/**
 * Homepage block types; each one is resolved by its own backend provider.
 */
enum HomepageBlockType: string
{
    case HeroBanner = 'hero_banner';
    case BestSellers = 'best_sellers';
    case Deals = 'deals';
    case NewReleases = 'new_releases';
    case GenreRail = 'genre_rail';
}
