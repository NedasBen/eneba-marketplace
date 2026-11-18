<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LookupOffersRequest;
use App\Http\Resources\OfferLookupResource;
use App\Models\Offer;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OfferController extends Controller
{
    /**
     * Current price, stock and status of the given offers. Unknown ids are
     * simply absent from the response (the cart treats them as unavailable).
     */
    public function index(LookupOffersRequest $request): AnonymousResourceCollection
    {
        $offers = Offer::query()
            ->whereIn('id', $request->offerIds())
            ->withStock()
            ->with(['seller:id,name', 'product:id,slug,name'])
            ->orderBy('id')
            ->get();

        return OfferLookupResource::collection($offers);
    }
}
