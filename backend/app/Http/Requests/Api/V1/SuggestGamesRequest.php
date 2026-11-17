<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Header autocomplete. A missing or too-short `q` is not an error: it simply
 * yields no suggestions, which suits search-as-you-type.
 */
class SuggestGamesRequest extends FormRequest
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
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
