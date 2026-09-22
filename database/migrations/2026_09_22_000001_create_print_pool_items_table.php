<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The print pool: cards gathered from anywhere — a scenario's entity deck, a
 * module's board cards, a character, a domain — to print together on one run.
 * A row points at a card by the same `group:id` key the print picker uses, so
 * it is a list of what to print, never a copy of a card. Like a saved print
 * setup, it is a fact about the designer's printer, not about the game, so
 * `design:export` knows nothing about it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_pool_items', function (Blueprint $table) {
            $table->id();
            $table->string('group', 20);
            $table->unsignedBigInteger('card_id');
            // Null prints as many copies as the card's own quantity.
            $table->unsignedInteger('qty')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['group', 'card_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_pool_items');
    }
};
