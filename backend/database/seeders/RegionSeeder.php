<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionSeeder extends Seeder
{
    /**
     * Activation regions, named the way Eneba labels keys.
     *
     * @var array<string, string>
     */
    public const REGIONS = [
        'global' => 'GLOBAL',
        'europe' => 'EUROPE',
        'north-america' => 'NORTH AMERICA',
        'united-kingdom' => 'UNITED KINGDOM',
        'latam' => 'LATAM',
    ];

    public function run(): void
    {
        $now = now();

        DB::table('regions')->insert(array_map(
            fn (string $slug, string $name): array => ['name' => $name, 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now],
            array_keys(self::REGIONS),
            self::REGIONS,
        ));
    }
}
