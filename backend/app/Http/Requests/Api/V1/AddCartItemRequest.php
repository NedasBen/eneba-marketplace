<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Cart\CartService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /cart/items`: an offer id and how many keys to add (default 1).
 * Only ids and counts are read; prices always come from the offer.
 */
class AddCartItemRequest extends FormRequest
{
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
            'offer_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
        ];
    }

    public function offerId(): int
    {
        return (int) $this->validated('offer_id');
    }

    public function quantity(): int
    {
        return (int) $this->validated('quantity', 1);
    }
}
