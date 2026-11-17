<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use Random\Randomizer;

/**
 * Helpers for fast, deterministic seeding.
 *
 * Seeders insert with multi-row INSERT statements instead of Eloquent models:
 * the catalog is ~30k rows and production seeds over the network to Neon,
 * where one round trip per row would be far too slow. Factories (and Faker,
 * a dev-only dependency) are reserved for tests.
 */
trait InsertsInChunks
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function insertInChunks(string $table, array $rows, int $rowsPerStatement = 500): void
    {
        foreach (array_chunk($rows, $rowsPerStatement) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * Uniform float in [$min, $max] (Randomizer::getFloat needs PHP 8.3).
     */
    protected function randomFloat(Randomizer $random, float $min, float $max): float
    {
        return $min + ($max - $min) * ($random->getInt(0, 1_000_000) / 1_000_000);
    }

    /**
     * A fake license key in the familiar "XXXXX-XXXXX-XXXXX" shape
     * (no 0/O/1/I, to stay readable).
     */
    protected function randomKeyCode(Randomizer $random): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $characters = '';

        for ($i = 0; $i < 15; $i++) {
            $characters .= $alphabet[$random->getInt(0, strlen($alphabet) - 1)];
        }

        return implode('-', str_split($characters, 5));
    }

    /**
     * Pick a key from a weight map (`key => positive weight`).
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, float|int>  $weights
     * @return TKey
     */
    protected function weightedPick(Randomizer $random, array $weights): int|string
    {
        $target = $this->randomFloat($random, 0, array_sum($weights));

        foreach ($weights as $key => $weight) {
            $target -= $weight;

            if ($target <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }
}
