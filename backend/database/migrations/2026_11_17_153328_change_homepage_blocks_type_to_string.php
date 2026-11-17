<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Block types are defined by provider classes (app/Services/Homepage/Blocks),
     * not by a database enum: adding a block type must not need a migration.
     * Rows whose type has no provider are skipped (and logged) at runtime.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE homepage_blocks DROP CONSTRAINT IF EXISTS homepage_blocks_type_check');
    }

    /**
     * Reverse the migrations. Fails if rows use types outside the original five.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE homepage_blocks ADD CONSTRAINT homepage_blocks_type_check CHECK (type IN ('hero_banner', 'best_sellers', 'deals', 'new_releases', 'genre_rail'))");
    }
};
