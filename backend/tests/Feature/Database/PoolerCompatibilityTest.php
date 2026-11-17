<?php

use Illuminate\Support\Facades\DB;

/*
 * Neon's pooled endpoint is PgBouncer in transaction mode. With server-side
 * prepared statements, pdo_pgsql frees each one with a SQL DEALLOCATE that
 * fails behind PgBouncer and silently aborts the surrounding transaction
 * (production: every homepage block and the seeder failed with 25P02).
 * Emulated prepares avoid server-side prepared statements entirely.
 */

it('creates no server-side prepared statements, so nothing needs DEALLOCATE behind a pooler', function (string $connection) {
    $db = DB::connection($connection);

    $db->select('SELECT ?::int AS answer', [42]);

    // With server-side prepares this very query would be a prepared statement and count itself.
    expect((int) $db->scalar('SELECT count(*) FROM pg_prepared_statements'))->toBe(0);
})->with(['pooled app connection' => 'pgsql', 'direct maintenance connection' => 'pgsql_direct']);

it('still binds values safely with emulated prepares', function () {
    $hostile = "x'); DROP TABLE products; --";

    expect(DB::scalar('SELECT ?::text', [$hostile]))->toBe($hostile)
        ->and(DB::scalar('SELECT ?::int + 1', [41]))->toBe(42);
});

it('writes and compares PHP booleans against boolean columns without casts', function () {
    // Laravel binds booleans as 0/1; inlined by emulated prepares that is an
    // integer literal Postgres rejects for a boolean column.
    DB::table('regions')->insert(['name' => 'Test', 'slug' => 'test-region', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('homepage_blocks')->insert([
        ['type' => 'best_sellers', 'title' => 'On', 'position' => 1, 'is_active' => true, 'settings' => '{}'],
        ['type' => 'best_sellers', 'title' => 'Off', 'position' => 2, 'is_active' => false, 'settings' => '{}'],
    ]);

    expect(DB::table('homepage_blocks')->where('is_active', true)->pluck('title')->all())->toBe(['On'])
        ->and(DB::table('homepage_blocks')->where('is_active', false)->pluck('title')->all())->toBe(['Off']);
});
