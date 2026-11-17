<?php

namespace App\Services\Homepage\Blocks;

use App\Models\HomepageBlock;
use App\Services\Homepage\ProductRailBlock;
use Illuminate\Database\Eloquent\Builder;

/** Already-released games, newest first, one product per game. */
class NewReleasesBlock extends ProductRailBlock
{
    public static function type(): string
    {
        return 'new_releases';
    }

    protected function products(HomepageBlock $block): Builder
    {
        $picks = $this->bestProductPerGame(
            $this->productsTable()
                ->join('games', 'games.id', '=', 'products.game_id')
                ->whereNotNull('games.released_at')
                ->whereDate('games.released_at', '<=', today()),
        );

        return $this->productsFromPicks($picks)
            ->join('games', 'games.id', '=', 'products.game_id')
            ->orderByDesc('games.released_at')
            ->orderBy('products.id');
    }

    protected function seeAllQuery(HomepageBlock $block): string
    {
        return '?sort=release_date';
    }
}
