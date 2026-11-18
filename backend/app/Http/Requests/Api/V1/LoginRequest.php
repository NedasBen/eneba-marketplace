<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Credential check with brute-force protection and no account enumeration:
 * a wrong password and an unknown email produce the same response, and an
 * unknown email still costs one bcrypt check so timing does not tell them apart.
 */
class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    /**
     * A real bcrypt hash (cost 12, like production) of a random string nobody knows.
     */
    private const TIMING_EQUALISER_HASH = '$2y$12$KA15yaPXfW9cNGS5PKQDAe9YYSG5wYfixQhQ9BLTLnv/NlzGwPhf2';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @throws ValidationException on wrong credentials (same message for unknown email and wrong password)
     * @throws HttpResponseException 429 with Retry-After once the attempts for this email+IP are used up
     */
    public function authenticate(): User
    {
        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw new HttpResponseException(response()->json(
                ['message' => trans('auth.throttle', ['seconds' => $seconds])],
                429,
                ['Retry-After' => (string) $seconds],
            ));
        }

        $user = User::query()->where('email', $this->validated('email'))->first();
        $password = (string) $this->validated('password');

        // Always run exactly one bcrypt check, whether or not the account exists.
        $valid = Hash::check($password, $user?->password ?? self::TIMING_EQUALISER_HASH) && $user !== null;

        if (! $valid) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        RateLimiter::clear($key);

        return $user;
    }

    private function throttleKey(): string
    {
        return 'login:'.$this->validated('email').'|'.$this->ip();
    }
}
