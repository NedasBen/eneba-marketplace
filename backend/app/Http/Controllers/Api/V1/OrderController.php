<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PayOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Checkout\OrderPaymentService;
use App\Services\Checkout\PaymentOutcome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Orders are always looked up through the signed-in user, so another
 * user's reference is the same 404 as one that doesn't exist.
 */
class OrderController extends Controller
{
    /** Messages for the simulated gateway's failure codes. */
    private const DECLINE_MESSAGES = [
        'card_declined' => 'Your card was declined. Try another card.',
        'insufficient_funds' => 'Your card has insufficient funds. Try another card.',
    ];

    public function show(Request $request, string $reference): OrderResource
    {
        $order = Order::query()->where('user_id', $request->user()->id)->where('reference', $reference)->first();

        if ($order === null) {
            throw new NotFoundHttpException('Order not found.');
        }

        return new OrderResource($order->load(OrderResource::RELATIONS));
    }

    /**
     * 200 paid (with keys; repeat calls don't charge again), 402 declined
     * (the order stays pending for a retry), 410 the reservation expired.
     */
    public function pay(PayOrderRequest $request, string $reference, OrderPaymentService $payments): JsonResponse
    {
        $outcome = $payments->pay($request->user(), $reference, $request->card());

        return match ($outcome->status) {
            PaymentOutcome::PAID => (new OrderResource($outcome->order->load(OrderResource::RELATIONS)))->response(),
            PaymentOutcome::DECLINED => response()->json([
                'message' => self::DECLINE_MESSAGES[$outcome->failureCode] ?? 'Your payment was declined. Try another card.',
                'failure_code' => $outcome->failureCode,
            ], 402),
            default => response()->json([
                'message' => 'This order has expired and its keys were released. Go back to your cart to check out again.',
            ], 410),
        };
    }
}
