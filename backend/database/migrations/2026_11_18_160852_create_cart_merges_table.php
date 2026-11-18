<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Processed guest-cart merges, so a retried POST /cart/merge with the same
     * merge id returns the first result instead of adding the items again.
     */
    public function up(): void
    {
        Schema::create('cart_merges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('merge_id');
            $table->jsonb('adjustments');
            $table->timestamp('created_at')->index();

            $table->unique(['user_id', 'merge_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_merges');
    }
};
