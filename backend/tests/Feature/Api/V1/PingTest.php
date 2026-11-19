<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('reports ok with a live database round-trip', function () {
    $this->getJson(route('api.v1.ping'))
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('database.connected', true)
        ->assertJsonStructure(['database' => ['latency_ms']])
        ->assertJsonMissingPath('environment')
        ->assertJsonMissingPath('database.server_time');
});

it('is rate limited per IP', function () {
    foreach (range(1, 60) as $request) {
        $this->getJson(route('api.v1.ping'))->assertOk();
    }

    $this->getJson(route('api.v1.ping'))->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->getJson(route('api.v1.ping'))->assertOk();
});

it('returns 503 when the database is unreachable', function () {
    DB::shouldReceive('scalar')
        ->once()
        ->andThrow(new QueryException('pgsql', 'select 1', [], new PDOException('connection refused')));

    $this->getJson(route('api.v1.ping'))
        ->assertServiceUnavailable()
        ->assertExactJson(['status' => 'degraded', 'database' => ['connected' => false]]);
});

it('allows cross-origin requests from the configured frontend origin', function () {
    $frontendOrigin = 'https://frontend.example';
    config(['cors.allowed_origins' => [$frontendOrigin]]);

    $this->getJson(route('api.v1.ping'), ['Origin' => $frontendOrigin])
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', $frontendOrigin);
});

it('never grants CORS access to other origins', function (array $allowedOrigins) {
    config(['cors.allowed_origins' => $allowedOrigins]);

    $allowOrigin = $this->getJson(route('api.v1.ping'), ['Origin' => 'https://evil.example'])
        ->headers->get('Access-Control-Allow-Origin');

    // One allowed origin is always named outright (the browser then refuses others); with several, others get none.
    expect($allowOrigin)->not->toBe('https://evil.example')->not->toBe('*');
})->with([
    'one frontend' => [['https://frontend.example']],
    'several frontends' => [['https://frontend.example', 'http://localhost:5173']],
]);

it('lets the frontend send authenticated JSON requests and read Retry-After', function () {
    $frontendOrigin = 'https://frontend.example';
    config(['cors.allowed_origins' => [$frontendOrigin]]);

    $this->call('OPTIONS', '/api/v1/checkout', server: [
        'HTTP_ORIGIN' => $frontendOrigin,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization, content-type, idempotency-key',
    ])
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', $frontendOrigin)
        ->assertHeader('Access-Control-Allow-Headers', 'authorization, content-type, idempotency-key');

    // A 429's Retry-After drives the login countdown, so the browser must be allowed to read it.
    $this->getJson(route('api.v1.ping'), ['Origin' => $frontendOrigin])
        ->assertHeader('Access-Control-Expose-Headers', 'Retry-After');
});
