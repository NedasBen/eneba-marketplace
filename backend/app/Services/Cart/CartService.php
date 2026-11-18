<?php

namespace App\Services\Cart;

use App\Models\CartItem;
use App\Models\CartMerge;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The signed-in user's cart. Prices and stock are always read from the
 * current offers; nothing price-related is accepted from the client.
 *
 * Every write runs in a transaction that first locks the user's row, so
 * concurrent requests for one cart (double clicks, two tabs, a retried
 * merge) are applied one after another and the caps always hold.
 */
final class CartService
{
    public const MAX_QUANTITY = 10;

    /**
     * Cart lines with their offer's current price and stock, oldest first.
     *
     * @return Collection<int, CartItem>
     */
    public function items(User $user): Collection
    {
        return $user->cartItems()
            ->with(['offer' => fn ($offer) => $offer->withStock()->with([
                'seller:id,name',
                'product' => fn ($product) => $product->forCard(),
            ])])
            ->orderBy('id')
            ->get();
    }

    /**
     * Add keys of an offer, on top of any already in the cart.
     *
     * @throws ValidationException when the offer can't be bought or the caps would be exceeded
     */
    public function add(User $user, int $offerId, int $quantity): void
    {
        $this->forUser($user, function () use ($user, $offerId, $quantity): void {
            $offer = $this->purchasableOffer($offerId, 'offer_id');
            $line = $user->cartItems()->where('offer_id', $offer->id)->first();
            $current = $line === null ? 0 : $line->quantity;

            $this->assertWithinLimit($current + $quantity, $offer, 'quantity', $current);

            CartItem::query()->updateOrCreate(
                ['user_id' => $user->id, 'offer_id' => $offer->id],
                ['quantity' => $current + $quantity, 'price_seen_cents' => $offer->price_cents],
            );
        });
    }

    /**
     * Set a line's quantity. The line must belong to the user (404 otherwise).
     *
     * @throws ValidationException
     */
    public function update(User $user, int $cartItemId, int $quantity): void
    {
        $this->forUser($user, function () use ($user, $cartItemId, $quantity): void {
            $line = $this->lineOf($user, $cartItemId);
            $offer = $this->purchasableOffer($line->offer_id, 'quantity');

            $this->assertWithinLimit($quantity, $offer, 'quantity');

            $line->update(['quantity' => $quantity]);
        });
    }

    /**
     * Remove a line. The line must belong to the user (404 otherwise).
     */
    public function remove(User $user, int $cartItemId): void
    {
        $this->forUser($user, function () use ($user, $cartItemId): void {
            $this->lineOf($user, $cartItemId)->delete();
        });
    }

    /**
     * Fold a guest cart into the user's cart, once per merge id.
     *
     * Quantities are added to existing lines and clamped to MAX_QUANTITY and
     * the offer's stock; unknown, inactive and sold-out offers are skipped.
     * Existing lines are never reduced. A merge id seen before returns that
     * merge's adjustments without touching the cart again.
     *
     * @param  list<array{offer_id: int, quantity: int}>  $guestItems
     * @return list<array{offer_id: int, kind: string, requested: int, quantity: int}>
     */
    public function merge(User $user, string $mergeId, array $guestItems): array
    {
        return $this->forUser($user, function () use ($user, $mergeId, $guestItems): array {
            $previous = CartMerge::query()->where('user_id', $user->id)->where('merge_id', $mergeId)->first();

            if ($previous !== null) {
                // jsonb doesn't keep key order: rebuild the shape so a retry matches the first answer exactly.
                return array_map(
                    fn (array $adjustment): array => $this->adjustment((int) $adjustment['offer_id'], (string) $adjustment['kind'], (int) $adjustment['requested'], (int) $adjustment['quantity']),
                    $previous->adjustments,
                );
            }

            // No scheduler on the free tier: drop this user's stale records here.
            CartMerge::query()->where('user_id', $user->id)->where('created_at', '<', now()->subDays(CartMerge::RETENTION_DAYS))->delete();

            $requested = $this->sumByOffer($guestItems);
            $offers = Offer::query()->whereIn('id', array_keys($requested))->withStock()->get()->keyBy('id');
            $lines = $user->cartItems()->whereIn('offer_id', array_keys($requested))->get()->keyBy('offer_id');
            $adjustments = [];

            foreach ($requested as $offerId => $quantity) {
                $offer = $offers->get($offerId);
                $current = $lines->get($offerId)?->quantity ?? 0;

                if ($offer === null || ! $offer->is_active) {
                    $adjustments[] = $this->adjustment($offerId, 'unavailable', $quantity, $current);

                    continue;
                }

                if ((int) $offer->stock === 0) {
                    $adjustments[] = $this->adjustment($offerId, 'out_of_stock', $quantity, $current);

                    continue;
                }

                $limit = $this->limitFor($offer);
                $merged = max($current, min($current + $quantity, $limit));

                if ($merged < $current + $quantity) {
                    $adjustments[] = $this->adjustment($offerId, 'clamped', $quantity, $merged);
                }

                if ($merged > $current) {
                    CartItem::query()->updateOrCreate(
                        ['user_id' => $user->id, 'offer_id' => $offerId],
                        ['quantity' => $merged, 'price_seen_cents' => $offer->price_cents],
                    );
                }
            }

            CartMerge::query()->create(['user_id' => $user->id, 'merge_id' => $mergeId, 'adjustments' => $adjustments]);

            return $adjustments;
        });
    }

    /**
     * The most keys of this offer one cart may hold right now.
     */
    public function limitFor(Offer $offer): int
    {
        return $offer->is_active ? max(0, min(self::MAX_QUANTITY, (int) $offer->stock)) : 0;
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private function forUser(User $user, callable $callback): mixed
    {
        return DB::transaction(function () use ($user, $callback): mixed {
            User::query()->whereKey($user->id)->lockForUpdate()->value('id');

            return $callback();
        });
    }

    /**
     * A line of this user's cart. Someone else's line id is indistinguishable
     * from a missing one (404), so ids can't be probed.
     */
    private function lineOf(User $user, int $cartItemId): CartItem
    {
        $line = $user->cartItems()->whereKey($cartItemId)->first();

        if ($line === null) {
            throw new NotFoundHttpException('Cart item not found.');
        }

        return $line;
    }

    /**
     * @throws ValidationException
     */
    private function purchasableOffer(int $offerId, string $field): Offer
    {
        /** @var Offer|null $offer */
        $offer = Offer::query()->withStock()->whereKey($offerId)->first();

        if ($offer === null || ! $offer->is_active) {
            throw ValidationException::withMessages([$field => 'This offer is no longer available.']);
        }

        if ((int) $offer->stock === 0) {
            throw ValidationException::withMessages([$field => 'This offer is out of stock.']);
        }

        return $offer;
    }

    /**
     * @throws ValidationException
     */
    private function assertWithinLimit(int $quantity, Offer $offer, string $field, int $alreadyInCart = 0): void
    {
        $limit = $this->limitFor($offer);

        if ($quantity <= $limit) {
            return;
        }

        $reason = $limit < self::MAX_QUANTITY
            ? "Only {$limit} ".($limit === 1 ? 'key is' : 'keys are').' available from this seller.'
            : 'You can buy at most '.self::MAX_QUANTITY.' keys of one offer.';

        throw ValidationException::withMessages([
            $field => $alreadyInCart > 0 ? "{$reason} You already have {$alreadyInCart} in your cart." : $reason,
        ]);
    }

    /**
     * One merge report entry: `quantity` is the line's quantity after the merge.
     *
     * @return array{offer_id: int, kind: string, requested: int, quantity: int}
     */
    private function adjustment(int $offerId, string $kind, int $requested, int $quantity): array
    {
        return ['offer_id' => $offerId, 'kind' => $kind, 'requested' => $requested, 'quantity' => $quantity];
    }

    /**
     * Combine repeated offers in a guest payload.
     *
     * @param  list<array{offer_id: int, quantity: int}>  $items
     * @return array<int, int> offer id => quantity
     */
    private function sumByOffer(array $items): array
    {
        $sums = [];

        foreach ($items as $item) {
            $offerId = (int) $item['offer_id'];
            $sums[$offerId] = ($sums[$offerId] ?? 0) + (int) $item['quantity'];
        }

        return $sums;
    }
}
