<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A product is one game on one platform for one region,
     * e.g. "ELDEN RING Steam Key EUROPE".
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->foreignId('platform_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->index()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('retail_price_cents');

            // Denormalised aggregates, maintained by ProductStockAggregator.
            $table->unsignedInteger('min_price_cents')->nullable();
            $table->unsignedInteger('offers_count')->default(0);
            $table->unsignedInteger('sold_count')->default(0)->index();

            $table->timestamps();

            // Leading game_id also serves "other editions of this game" lookups.
            $table->unique(['game_id', 'platform_id', 'region_id']);
            $table->index(['platform_id', 'min_price_cents']);
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_retail_price_positive CHECK (retail_price_cents > 0)');
        DB::statement('CREATE INDEX products_min_price_in_stock_index ON products (min_price_cents) WHERE min_price_cents IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
