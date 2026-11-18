<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    /**
     * Create a pending order from the server cart and reserve its keys:
     * 201 when created, 200 when the Idempotency-Key was already used.
     */
    public function store(CheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        [$order, $created] = $checkout->checkout($request->user(), $request->idempotencyKey());

        return (new OrderResource($order->load(OrderResource::RELATIONS)))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }
}
