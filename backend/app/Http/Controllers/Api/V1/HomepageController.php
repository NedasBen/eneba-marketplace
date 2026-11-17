<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Homepage\HomepageBuilder;
use Illuminate\Http\JsonResponse;

class HomepageController extends Controller
{
    /**
     * Active homepage blocks in order, each with type, layout, title and data.
     */
    public function __invoke(HomepageBuilder $homepage): JsonResponse
    {
        return response()->json(['data' => $homepage->blocks()]);
    }
}
