<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListProductsRequest;
use App\Http\Resources\ProductCardResource;
use App\Services\Catalog\ProductCatalogQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Paginated, filtered and sorted store listing.
     */
    public function index(ListProductsRequest $request, ProductCatalogQuery $catalog): AnonymousResourceCollection
    {
        $filters = $request->catalogFilters();

        $products = $catalog->products($filters)
            ->forCard()
            ->paginate($filters->perPage)
            ->withQueryString();

        // `query` lets the UI show "Showing results for witcher" after a correction.
        return ProductCardResource::collection($products)
            ->additional(['query' => $filters->search?->toArray()]);
    }
}
