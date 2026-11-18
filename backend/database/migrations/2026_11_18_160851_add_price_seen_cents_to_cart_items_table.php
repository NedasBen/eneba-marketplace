<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The offer's price when the line was added. Display only (for the
     * "price changed since you added it" notice); totals and checkout always
     * use the offer's current price.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->unsignedInteger('price_seen_cents')->nullable()->after('quantity');
        });

        DB::statement('UPDATE cart_items SET price_seen_cents = offers.price_cents FROM offers WHERE offers.id = cart_items.offer_id');

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unsignedInteger('price_seen_cents')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('price_seen_cents');
        });
    }
};
