<?php

namespace App\Services\Homepage\Blocks;

use App\Models\HomepageBlock;
use App\Services\Homepage\ProductRailBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Best sellers within one genre (`settings.genre`, a genre slug), one product
 * per game. A missing or unknown genre yields nothing, so the block is omitted.
 */
class GenreRailBlock extends ProductRailBlock
{
    public static function type(): string
    {
        return 'genre_rail';
    }

    protected function products(HomepageBlock $block): ?Builder
    {
        $genre = $block->settings['genre'] ?? null;

        if (! is_string($genre) || $genre === '') {
            return null;
        }

        $picks = $this->bestProductPerGame(
            $this->productsTable()->whereExists(fn (QueryBuilder $inGenre) => $inGenre
                ->selectRaw('1')
                ->from('game_genre')
                ->join('genres', 'genres.id', '=', 'game_genre.genre_id')
                ->whereColumn('game_genre.game_id', 'products.game_id')
                ->where('genres.slug', $genre)),
        );

        return $this->productsFromPicks($picks)
            ->orderByDesc('products.sold_count')
            ->orderBy('products.id');
    }

    protected function seeAllQuery(HomepageBlock $block): string
    {
        return '?genre='.rawurlencode((string) $block->settings['genre']);
    }
}
