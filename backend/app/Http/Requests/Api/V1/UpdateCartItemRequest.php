<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Cart\CartService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `PATCH /cart/items/{id}`: the line's new quantity.
 */
class UpdateCartItemRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
        ];
    }

    public function quantity(): int
    {
        return (int) $this->validated('quantity');
    }
}
