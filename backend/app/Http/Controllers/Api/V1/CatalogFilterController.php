<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListProductsRequest;
use App\Services\Catalog\ProductCatalogQuery;
use Illuminate\Http\JsonResponse;

class CatalogFilterController extends Controller
{
    /**
     * Filter options with live counts, price range and sort options for the
     * store sidebar, so the frontend hardcodes none of them.
     */
    public function __invoke(ListProductsRequest $request, ProductCatalogQuery $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->facets($request->catalogFilters())]);
    }
}
