<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Cart\CartService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /cart/merge`: the guest cart from localStorage plus a client-made
 * merge id (UUID) that makes retries safe. Only offer ids and quantities are
 * read; any prices the client sends are ignored.
 */
class MergeCartRequest extends FormRequest
{
    public const MAX_ITEMS = 50;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'merge_id' => ['required', 'uuid'],
            'items' => ['present', 'array', 'max:'.self::MAX_ITEMS],
            'items.*.offer_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
        ];
    }

    public function mergeId(): string
    {
        return strtolower((string) $this->validated('merge_id'));
    }

    /**
     * @return list<array{offer_id: int, quantity: int}>
     */
    public function guestItems(): array
    {
        return array_values(array_map(
            fn (array $item): array => ['offer_id' => (int) $item['offer_id'], 'quantity' => (int) $item['quantity']],
            $this->validated('items', []),
        ));
    }
}
