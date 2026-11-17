<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps a Postgres `text[]` column to a PHP list of strings.
 *
 * @implements CastsAttributes<list<string>, list<string>>
 */
class PostgresTextArray implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '{}') {
            return [];
        }

        return array_map(
            fn (string $element): string => stripcslashes(trim($element, '"')),
            str_getcsv(trim((string) $value, '{}'), escape: '\\'),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return self::toLiteral((array) $value);
    }

    /**
     * @param  array<int, string>  $values
     */
    public static function toLiteral(array $values): string
    {
        return '{'.implode(',', array_map(
            fn (string $value): string => '"'.addcslashes($value, '"\\').'"',
            array_values($values),
        )).'}';
    }
}
