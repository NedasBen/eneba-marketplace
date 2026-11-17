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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->index()->constrained()->restrictOnDelete();
            $table->unsignedInteger('price_cents');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'seller_id']);
        });

        DB::statement('ALTER TABLE offers ADD CONSTRAINT offers_price_positive CHECK (price_cents > 0)');
        DB::statement('CREATE INDEX offers_active_product_price_index ON offers (product_id, price_cents) WHERE is_active');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
