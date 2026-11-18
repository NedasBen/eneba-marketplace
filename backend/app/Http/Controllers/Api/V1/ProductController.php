<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListProductsRequest;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductDetailResource;
use App\Models\Product;
use App\Models\Seller;
use App\Services\Catalog\ProductCatalogQuery;
use Illuminate\Http\JsonResponse;
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

    /**
     * The product page. Unknown slugs 404 through route model binding.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['game.genres:id,name,slug', 'platform', 'region']);

        // Purchasable offers only, cheapest first; equal prices favour the better-rated seller.
        $offers = $product->offers()
            ->purchasable()
            ->withStock()
            ->with('seller:id,name,slug,rating,feedback_count,country_code')
            ->orderBy('price_cents')
            ->orderByDesc(Seller::query()->select('rating')->whereColumn('sellers.id', 'offers.seller_id'))
            ->orderBy('offers.id')
            ->get();

        // Every edition of the game (this one included), in a stable platform/region order.
        $editions = Product::query()
            ->where('game_id', $product->game_id)
            ->with(['platform:id,slug,name,activation_service', 'region:id,slug,name'])
            ->orderBy('platform_id')
            ->orderBy('region_id')
            ->get();

        return response()->json(['data' => (new ProductDetailResource($product, $offers, $editions))->resolve()]);
    }
}
