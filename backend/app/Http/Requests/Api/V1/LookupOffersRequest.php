<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /offers?ids=1,2,3` (or `ids[]=1&ids[]=2`): up to 50 offer ids.
 */
class LookupOffersRequest extends FormRequest
{
    public const MAX_IDS = 50;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->query('ids');

        if (is_string($ids)) {
            $this->merge(['ids' => array_values(array_filter(array_map('trim', explode(',', $ids)), fn (string $id): bool => $id !== ''))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function offerIds(): array
    {
        return array_map('intval', $this->validated('ids'));
    }
}
