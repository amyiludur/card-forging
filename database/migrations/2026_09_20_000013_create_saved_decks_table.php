<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A deck build saved under a name — the character, the domain and how many
 * copies of each pool card, the same three things the query string already
 * carries on the deck builder. Saving under a name already in use overwrites
 * it, the same way a print preset does.
 *
 * This is not the deck itself: a character or domain deleted after a save
 * leaves the name pointing at a slug that no longer resolves, and the deck
 * builder reports that rather than pretending otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_decks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('build');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_decks');
    }
};
