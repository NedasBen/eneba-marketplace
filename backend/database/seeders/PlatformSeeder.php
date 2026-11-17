<?php

namespace Database\Seeders;

use App\Enums\GamePlatform;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('platforms')->insert(array_map(fn (GamePlatform $platform): array => [
            'name' => $platform->label(),
            'slug' => $platform->value,
            'activation_service' => $platform->activationService(),
            'created_at' => $now,
            'updated_at' => $now,
        ], GamePlatform::cases()));
    }
}
