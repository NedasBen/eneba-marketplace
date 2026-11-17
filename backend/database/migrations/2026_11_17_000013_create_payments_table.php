<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per payment attempt; a declined attempt leaves the order pending.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->index()->constrained()->restrictOnDelete();
            $table->string('provider');
            $table->string('provider_reference');
            $table->enum('status', ['succeeded', 'declined']);
            $table->string('failure_code')->nullable();
            $table->unsignedInteger('amount_cents');
            $table->char('card_last4', 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
