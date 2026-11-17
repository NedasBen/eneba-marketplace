<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('steam_app_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('short_description');
            $table->text('description');
            $table->string('cover_image_url');
            $table->string('header_image_url');
            $table->string('hero_image_url')->nullable();
            $table->jsonb('screenshots')->default('[]');
            $table->date('released_at')->nullable()->index();
            $table->unsignedSmallInteger('metacritic')->nullable();
            $table->string('developer')->nullable();
            $table->string('publisher')->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE INDEX games_name_trgm_index ON games USING gin (name gin_trgm_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
