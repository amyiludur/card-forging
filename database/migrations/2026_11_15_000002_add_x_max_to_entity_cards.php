<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The most an X-cost card's X can come to — "Create X Tentacles (max 3)".
 *
 * Nullable, and null is the whole of "no cap": only an X card has one, and an X
 * card the designer has not capped prints and plays the way it always did. The
 * reveal still drains the pool the way the rules say; this is what the card's
 * effect counts up to, printed beside the X so the player can see it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_cards', function (Blueprint $table) {
            $table->unsignedSmallInteger('omen_x_max')->nullable()->after('omen_is_x');
        });
    }

    public function down(): void
    {
        Schema::table('entity_cards', function (Blueprint $table) {
            $table->dropColumn('omen_x_max');
        });
    }
};
