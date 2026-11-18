<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /checkout` with an `Idempotency-Key: <uuid>` header. The client
 * keeps the key for one cart, so a double click or a retry after a lost
 * response returns the same order instead of creating a second one.
 */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'The Idempotency-Key header is required.',
            'idempotency_key.uuid' => 'The Idempotency-Key header must be a UUID.',
        ];
    }

    public function idempotencyKey(): string
    {
        return strtolower((string) $this->validated('idempotency_key'));
    }
}
