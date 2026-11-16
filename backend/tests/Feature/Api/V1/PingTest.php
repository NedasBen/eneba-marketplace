<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('reports ok with a live database round-trip', function () {
    $this->getJson(route('api.v1.ping'))
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('database.connected', true)
        ->assertJsonStructure(['database' => ['server_time', 'latency_ms']]);
});

it('returns 503 when the database is unreachable', function () {
    DB::shouldReceive('scalar')
        ->once()
        ->andThrow(new QueryException('pgsql', 'select now()', [], new PDOException('connection refused')));

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
