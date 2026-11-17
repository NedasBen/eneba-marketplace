<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerSeeder extends Seeder
{
    /**
     * Fictional sellers: name => [positive feedback %, feedback count, country].
     *
     * @var array<string, array{0: float, 1: int, 2: string}>
     */
    private const SELLERS = [
        'PixelVault' => [99.8, 88_412, 'LT'],
        'KeyForge' => [99.5, 61_230, 'PL'],
        'Baltic Games' => [99.1, 24_870, 'LV'],
        'NordicKeys' => [98.9, 17_655, 'SE'],
        'LevelUp Store' => [98.4, 9_812, 'DE'],
        'GameNest' => [97.9, 6_340, 'NL'],
        'Vortex Digital' => [97.2, 3_918, 'GB'],
        'StarCrate' => [96.5, 2_241, 'US'],
        'BlueFox Games' => [95.8, 1_107, 'FR'],
        'RetroRealm' => [94.1, 684, 'ES'],
        'QuickKey' => [91.7, 312, 'RO'],
        'Lootbox Traders' => [88.3, 157, 'TR'],
    ];

    public function run(): void
    {
        $now = now();
        $rows = [];

        foreach (self::SELLERS as $name => [$rating, $feedbackCount, $countryCode]) {
            $rows[] = [
                'name' => $name,
                'slug' => Str::slug($name),
                'rating' => $rating,
                'feedback_count' => $feedbackCount,
                'country_code' => $countryCode,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('sellers')->insert($rows);
    }
}
