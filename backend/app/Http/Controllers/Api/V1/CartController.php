<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddCartItemRequest;
use App\Http\Requests\Api\V1\MergeCartRequest;
use App\Http\Requests\Api\V1\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\User;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's cart. Every endpoint answers with the whole cart so
 * the SPA can replace its copy in one go. Lines are always looked up through
 * the current user, so another user's line id is a 404.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function index(Request $request): CartResource
    {
        return $this->cartFor($request->user());
    }

    public function store(AddCartItemRequest $request): CartResource
    {
        $this->cart->add($request->user(), $request->offerId(), $request->quantity());

        return $this->cartFor($request->user());
    }

    public function update(UpdateCartItemRequest $request, int $cartItem): CartResource
    {
        $this->cart->update($request->user(), $cartItem, $request->quantity());

        return $this->cartFor($request->user());
    }

    public function destroy(Request $request, int $cartItem): CartResource
    {
        $this->cart->remove($request->user(), $cartItem);

        return $this->cartFor($request->user());
    }

    /**
     * Fold the guest cart in. Repeating a merge id returns the first result.
     */
    public function merge(MergeCartRequest $request): JsonResponse
    {
        $adjustments = $this->cart->merge($request->user(), $request->mergeId(), $request->guestItems());

        return $this->cartFor($request->user())
            ->additional(['adjustments' => $adjustments])
            ->response();
    }

    private function cartFor(User $user): CartResource
    {
        return new CartResource($this->cart->items($user));
    }
}
