<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The auth guard caches the resolved user for the lifetime of the app
 * instance; forget it so each request re-authenticates from its token, as
 * separate HTTP requests do in production.
 */
function asBearer(string $token, string $method, string $uri): TestResponse
{
    app('auth')->forgetGuards();

    return test()->json($method, $uri, [], ['Authorization' => "Bearer {$token}"]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function registerAccount(array $overrides = []): TestResponse
{
    return test()->postJson('/api/v1/auth/register', array_replace([
        'name' => 'Ann Buyer',
        'email' => 'ann@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ], $overrides));
}

function login(string $email, string $password): TestResponse
{
    return test()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
}

describe('register', function () {
    it('creates the account and returns a token that works', function () {
        $this->freezeTime();

        $response = registerAccount(['email' => '  Ann@Example.COM ']);

        $response->assertCreated()
            ->assertJsonPath('user.name', 'Ann Buyer')
            ->assertJsonPath('user.email', 'ann@example.com')
            ->assertJsonPath('expires_at', now()->addDays(7)->toIso8601String())
            ->assertJsonMissingPath('user.password');

        asBearer($response->json('token'), 'GET', '/api/v1/auth/me')->assertOk()->assertJsonPath('user.email', 'ann@example.com');
    });

    it('rejects invalid input', function (array $overrides, string $field) {
        User::factory()->create(['email' => 'taken@example.com']);

        registerAccount($overrides)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'invalid email' => [['email' => 'not-an-email'], 'email'],
        'taken email' => [['email' => 'taken@example.com'], 'email'],
        'taken email in other casing' => [['email' => 'TAKEN@Example.com'], 'email'],
        'too short' => [['password' => 'abc12', 'password_confirmation' => 'abc12'], 'password'],
        'letters only' => [['password' => 'onlyletters', 'password_confirmation' => 'onlyletters'], 'password'],
        'numbers only' => [['password' => '1234567890', 'password_confirmation' => '1234567890'], 'password'],
        'confirmation mismatch' => [['password_confirmation' => 'different123'], 'password'],
    ]);
});

describe('login', function () {
    it('returns a working token for correct credentials, with any email casing', function () {
        User::factory()->create(['email' => 'ann@example.com', 'password' => 'secret123']);

        $response = login('ANN@example.com', 'secret123')->assertOk()->assertJsonPath('user.email', 'ann@example.com');

        asBearer($response->json('token'), 'GET', '/api/v1/auth/me')->assertOk();
    });

    it('answers a wrong password and an unknown email identically', function () {
        User::factory()->create(['email' => 'ann@example.com', 'password' => 'secret123']);

        $wrongPassword = login('ann@example.com', 'wrong-password1');
        $unknownEmail = login('nobody@example.com', 'wrong-password1');

        $wrongPassword->assertUnprocessable();
        expect($unknownEmail->status())->toBe($wrongPassword->status())
            ->and($unknownEmail->json())->toBe($wrongPassword->json())
            ->and($wrongPassword->json('errors.email.0'))->toBe('These credentials do not match our records.');
    });

    it('locks an email+IP for a minute after five failed attempts, even for the right password', function () {
        User::factory()->create(['email' => 'ann@example.com', 'password' => 'secret123']);

        foreach (range(1, 5) as $attempt) {
            login('ann@example.com', 'wrong-password1')->assertUnprocessable();
        }

        login('ann@example.com', 'secret123')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Too many login attempts'));

        $this->travel(61)->seconds();

        login('ann@example.com', 'secret123')->assertOk();
    });

    it('resets the failure count after a successful login', function () {
        User::factory()->create(['email' => 'ann@example.com', 'password' => 'secret123']);

        foreach (range(1, 4) as $attempt) {
            login('ann@example.com', 'wrong-password1');
        }
        login('ann@example.com', 'secret123')->assertOk();

        foreach (range(1, 5) as $attempt) {
            login('ann@example.com', 'wrong-password1')->assertUnprocessable();
        }
    });

    it('limits one IP to 20 attempts a minute across different emails', function () {
        foreach (range(1, 20) as $attempt) {
            login("user{$attempt}@example.com", 'wrong-password1')->assertUnprocessable();
        }

        login('user21@example.com', 'wrong-password1')->assertTooManyRequests();
    });
});

describe('tokens', function () {
    it('rejects a token once it has expired', function () {
        $token = registerAccount()->json('token');

        $this->travel(7)->days();
        $this->travel(1)->minutes();

        asBearer($token, 'GET', '/api/v1/auth/me')->assertUnauthorized();
    });

    it('revokes only the current token on logout', function () {
        User::factory()->create(['email' => 'ann@example.com', 'password' => 'secret123']);
        $laptop = login('ann@example.com', 'secret123')->json('token');
        $phone = login('ann@example.com', 'secret123')->json('token');

        asBearer($laptop, 'POST', '/api/v1/auth/logout')->assertNoContent();

        asBearer($laptop, 'GET', '/api/v1/auth/me')->assertUnauthorized();
        asBearer($phone, 'GET', '/api/v1/auth/me')->assertOk();
        expect(PersonalAccessToken::query()->count())->toBe(1);
    });

    it('rejects a made-up token', function () {
        asBearer('1|not-a-real-token', 'GET', '/api/v1/auth/me')->assertUnauthorized();
    });

    it('answers 401 in JSON without a token, even without an Accept header', function () {
        $this->get('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->post('/api/v1/auth/logout')->assertUnauthorized();
    });
});
