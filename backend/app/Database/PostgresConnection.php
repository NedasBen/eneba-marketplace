<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;

/**
 * Postgres connection that binds booleans as 'true'/'false' literals.
 *
 * Laravel turns PHP booleans into the integers 0/1. With server-side
 * prepares Postgres receives them as untyped parameters and coerces them to
 * boolean; with emulated prepares (required behind Neon's PgBouncer pooler,
 * see config/database.php) PDO inlines them as integer literals, and
 * `INSERT … is_active = 1` fails with "column is of type boolean but
 * expression is of type integer". Quoted 'true'/'false' are untyped literals
 * that Postgres coerces correctly in both modes.
 */
class PostgresConnection extends BasePostgresConnection
{
    /**
     * @param  array<int|string, mixed>  $bindings
     * @return array<int|string, mixed>
     */
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return parent::prepareBindings($bindings);
    }
}
