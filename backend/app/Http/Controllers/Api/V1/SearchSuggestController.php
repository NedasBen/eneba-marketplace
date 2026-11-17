<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SuggestGamesRequest;
use App\Services\Search\GameSearch;
use Illuminate\Http\JsonResponse;

class SearchSuggestController extends Controller
{
    /**
     * Top matching games (not every platform/region product) for the header
     * autocomplete, plus the original and corrected query.
     */
    public function __invoke(SuggestGamesRequest $request, GameSearch $gameSearch): JsonResponse
    {
        $term = $gameSearch->term($request->validated('q'));

        return response()->json([
            'data' => $term === null ? [] : $gameSearch->suggest($term),
            'query' => $term?->toArray(),
        ]);
    }
}
