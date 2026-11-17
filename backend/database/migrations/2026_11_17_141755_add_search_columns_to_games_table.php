<?php

use App\Casts\PostgresTextArray;
use App\Services\Search\GameSearchText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Search columns derived from games.name (see GameSearchText):
     *  - search_name:   normalized name, trigram-indexed for fuzzy/word-prefix matching;
     *  - search_tokens: its words plus acronyms ("gta", "gta5", "rdr2"), GIN-indexed
     *                   for exact token containment.
     *
     * Existing rows (a few hundred games at most) are backfilled here, so
     * already-seeded databases such as production get working search on deploy.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->text('search_name')->default('');
        });

        DB::statement("ALTER TABLE games ADD COLUMN search_tokens text[] NOT NULL DEFAULT '{}'");

        DB::table('games')->select(['id', 'name'])->orderBy('id')->chunkById(200, function ($games): void {
            foreach ($games as $game) {
                DB::table('games')->where('id', $game->id)->update([
                    'search_name' => GameSearchText::normalize($game->name),
                    'search_tokens' => PostgresTextArray::toLiteral(GameSearchText::tokens($game->name)),
                ]);
            }
        });

        // Search no longer reads games.name directly.
        DB::statement('DROP INDEX IF EXISTS games_name_trgm_index');
        DB::statement('CREATE INDEX games_search_name_trgm_index ON games USING gin (search_name gin_trgm_ops)');
        DB::statement('CREATE INDEX games_search_tokens_index ON games USING gin (search_tokens)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS games_search_tokens_index');
        DB::statement('DROP INDEX IF EXISTS games_search_name_trgm_index');

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['search_name', 'search_tokens']);
        });

        DB::statement('CREATE INDEX games_name_trgm_index ON games USING gin (name gin_trgm_ops)');
    }
};
