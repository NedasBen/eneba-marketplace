<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Individual license keys held in stock by an offer.
     */
    public function up(): void
    {
        Schema::create('product_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->restrictOnDelete();
            // Stored with Laravel's `encrypted` cast, hence text rather than string.
            $table->text('code');
            $table->enum('status', ['available', 'reserved', 'sold'])->default('available');
            $table->timestamp('reserved_until')->nullable();
            $table->foreignId('order_item_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->timestamps();

            // Stock counts and FOR UPDATE SKIP LOCKED key picking per offer.
            $table->index(['offer_id', 'status', 'reserved_until']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE product_keys ADD CONSTRAINT product_keys_state_consistent CHECK (
                (status = 'available' AND order_item_id IS NULL AND reserved_until IS NULL)
                OR (status = 'reserved' AND order_item_id IS NOT NULL AND reserved_until IS NOT NULL)
                OR (status = 'sold' AND order_item_id IS NOT NULL)
            )
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_keys');
    }
};
