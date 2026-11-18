<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Payments\CardDetails;
use App\Services\Payments\TestCards;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * `POST /orders/{reference}/pay`: card details for the simulated gateway.
 * Spaces and dashes in the number are ignored. Only the demo's test cards
 * are accepted, so a real card number is refused before anything happens.
 */
class PayOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'card_number' => preg_replace('/[\s-]+/', '', (string) $this->input('card_number', '')),
            'cardholder_name' => trim((string) $this->input('cardholder_name', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'card_number' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! TestCards::passesLuhn((string) $value)) {
                        $fail('This card number is not valid.');
                    } elseif (! TestCards::isTestCard((string) $value)) {
                        $fail('This is a demo: use one of the test cards.');
                    }
                },
            ],
            'expiry_month' => ['required', 'integer', 'between:1,12'],
            'expiry_year' => ['required', 'integer', 'between:2000,2100'],
            'cvc' => ['required', 'string', 'regex:/^\d{3}$/'],
            'cardholder_name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $month = $this->integer('expiry_month');
                $year = $this->integer('expiry_year');

                if ($validator->errors()->hasAny(['expiry_month', 'expiry_year'])) {
                    return;
                }

                // A card is valid through the last day of its expiry month.
                if ($year < now()->year || ($year === now()->year && $month < now()->month)) {
                    $validator->errors()->add('expiry_month', 'This card has expired.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cvc.regex' => 'The CVC must be 3 digits.',
        ];
    }

    public function card(): CardDetails
    {
        return new CardDetails(
            number: (string) $this->validated('card_number'),
            expiryMonth: (int) $this->validated('expiry_month'),
            expiryYear: (int) $this->validated('expiry_year'),
            cvc: (string) $this->validated('cvc'),
            holderName: (string) $this->validated('cardholder_name'),
        );
    }
}
