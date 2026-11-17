<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ProductSort;
use App\Services\Catalog\CatalogFilters;
use App\Services\Search\GameSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Store filters for GET /products and GET /catalog/filters.
 *
 * Facets accept `platform[]=pc&platform[]=xbox` or the shorter, shareable
 * `platform=pc,xbox`. Unknown slugs are rejected rather than ignored, so a
 * typo never silently means "no filter".
 */
class ListProductsRequest extends FormRequest
{
    private const FACETS = ['platform', 'region', 'genre'];

    private const MAX_FACET_VALUES = 20;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (self::FACETS as $facet) {
            $value = $this->query($facet);

            if (is_string($value)) {
                $this->merge([$facet => array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $slug): bool => $slug !== ''))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'platform' => ['sometimes', 'array', 'max:'.self::MAX_FACET_VALUES],
            'platform.*' => ['string', 'distinct', Rule::exists('platforms', 'slug')],
            'region' => ['sometimes', 'array', 'max:'.self::MAX_FACET_VALUES],
            'region.*' => ['string', 'distinct', Rule::exists('regions', 'slug')],
            'genre' => ['sometimes', 'array', 'max:'.self::MAX_FACET_VALUES],
            'genre.*' => ['string', 'distinct', Rule::exists('genres', 'slug')],
            'price_min' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'price_max' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'in_stock' => ['sometimes', Rule::in(['0', '1', 'true', 'false'])],
            'sort' => ['sometimes', Rule::enum(ProductSort::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.CatalogFilters::MAX_PER_PAGE],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $min = $this->input('price_min');
                $max = $this->input('price_max');

                if (is_numeric($min) && is_numeric($max) && (float) $max < (float) $min) {
                    $validator->errors()->add('price_max', 'The maximum price must be greater than or equal to the minimum price.');
                }
            },
        ];
    }

    /**
     * With a usable query the default sort is relevance; without one,
     * relevance falls back to popularity.
     */
    public function catalogFilters(): CatalogFilters
    {
        $search = app(GameSearch::class)->term($this->validated('q'));
        $requestedSort = ProductSort::tryFrom((string) $this->validated('sort', ''));
        $sort = $requestedSort ?? ($search === null ? ProductSort::Popularity : ProductSort::Relevance);

        if ($sort->requiresQuery() && $search === null) {
            $sort = ProductSort::Popularity;
        }

        return new CatalogFilters(
            search: $search,
            platformSlugs: $this->validated('platform', []),
            regionSlugs: $this->validated('region', []),
            genreSlugs: $this->validated('genre', []),
            priceMinCents: $this->eurosToCents($this->validated('price_min')),
            priceMaxCents: $this->eurosToCents($this->validated('price_max')),
            inStock: $this->boolean('in_stock', true),
            sort: $sort,
            perPage: (int) $this->validated('per_page', CatalogFilters::DEFAULT_PER_PAGE),
        );
    }

    private function eurosToCents(mixed $euros): ?int
    {
        return is_numeric($euros) ? (int) round((float) $euros * 100) : null;
    }
}
