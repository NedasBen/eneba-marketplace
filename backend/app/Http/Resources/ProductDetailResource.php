<?php

namespace App\Http\Resources;

use App\Models\Genre;
use App\Models\Offer;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The product page: the product card fields, full game details, every
 * purchasable offer (cheapest first) and the game's other editions.
 *
 * Expects `game.genres`, `platform` and `region` loaded; offers and editions
 * are passed in so the controller controls their queries.
 *
 * @mixin Product
 */
class ProductDetailResource extends ProductCardResource
{
    /**
     * @param  Collection<int, Offer>  $offers
     * @param  Collection<int, Product>  $editions
     */
    public function __construct(Product $product, private readonly Collection $offers, private readonly Collection $editions)
    {
        parent::__construct($product);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $game = $this->game;

        return [
            ...parent::toArray($request),
            'game' => [
                'name' => $game->name,
                'slug' => $game->slug,
                'short_description' => $game->short_description,
                'description' => $game->description,
                'cover_image_url' => $game->cover_image_url,
                'header_image_url' => $game->header_image_url,
                'hero_image_url' => $game->hero_image_url,
                'screenshots' => array_values($game->screenshots ?? []),
                'genres' => $game->genres->sortBy('name')->values()->map(fn (Genre $genre): array => ['slug' => $genre->slug, 'name' => $genre->name])->all(),
                'developer' => $game->developer,
                'publisher' => $game->publisher,
                'released_at' => $game->released_at?->toDateString(),
                'metacritic' => $game->metacritic,
            ],
            'offers' => OfferResource::collection($this->offers)->resolve($request),
            'editions' => $this->editions->map(fn (Product $edition): array => [
                'slug' => $edition->slug,
                'name' => $edition->name,
                'platform' => ['slug' => $edition->platform->slug, 'name' => $edition->platform->name, 'activation_service' => $edition->platform->activation_service],
                'region' => ['slug' => $edition->region->slug, 'name' => $edition->region->name],
                'price_from' => Money::fromNullableCents($edition->min_price_cents),
                'in_stock' => $edition->min_price_cents !== null,
                'is_current' => $edition->id === $this->id,
            ])->values()->all(),
        ];
    }
}
