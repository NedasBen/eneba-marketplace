<?php

namespace App\Services\Catalog;

use App\Enums\ProductSort;

/**
 * Validated store filters, shared by the product listing and the filters
 * endpoint. Prices are in cents; slugs come from the database tables.
 */
final readonly class CatalogFilters
{
    public const DEFAULT_PER_PAGE = 24;

    public const MAX_PER_PAGE = 48;

    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [12, 24, 48];

    /**
     * @param  list<string>  $platformSlugs
     * @param  list<string>  $regionSlugs
     * @param  list<string>  $genreSlugs
     */
    public function __construct(
        public array $platformSlugs = [],
        public array $regionSlugs = [],
        public array $genreSlugs = [],
        public ?int $priceMinCents = null,
        public ?int $priceMaxCents = null,
        public bool $inStock = true,
        public ProductSort $sort = ProductSort::Popularity,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {}

    /**
     * The same filters without one facet, used for that facet's own counts
     * (selecting PC must not zero out the Xbox count).
     */
    public function without(string $facet): self
    {
        return new self(
            platformSlugs: $facet === 'platform' ? [] : $this->platformSlugs,
            regionSlugs: $facet === 'region' ? [] : $this->regionSlugs,
            genreSlugs: $facet === 'genre' ? [] : $this->genreSlugs,
            priceMinCents: $facet === 'price' ? null : $this->priceMinCents,
            priceMaxCents: $facet === 'price' ? null : $this->priceMaxCents,
            inStock: $this->inStock,
            sort: $this->sort,
            perPage: $this->perPage,
        );
    }
}
