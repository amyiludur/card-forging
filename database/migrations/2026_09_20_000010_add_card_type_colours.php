<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colour, on the two things that carry an identity: a card type and a
 * character.
 *
 * A card type gains a colour and an icon, and may belong to a scenario rather
 * than to the shared library — the Kraken can have a Tide type no other
 * scenario has. Slugs stay unique across the whole table, shared or owned, so
 * a type filter and a design file's `"type": "tide"` are never ambiguous.
 *
 * A character gains two colours, which the character card prints as a slight
 * gradient. Both are nullable: a type or a character with no colour prints the
 * dark head it always printed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_types', function (Blueprint $table) {
            // #rrggbb, or null for the card's default dark head.
            $table->string('colour', 7)->nullable()->after('description');
            // The five shipped types are named after their icon, so the icon
            // falls back to the slug and this is only set when they differ —
            // or when a type the designer added needs one at all.
            $table->string('icon')->nullable()->after('colour');
            $table->foreignId('scenario_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->string('colour', 7)->nullable()->after('identity');
            $table->string('colour_secondary', 7)->nullable()->after('colour');
        });
    }

    public function down(): void
    {
        Schema::table('card_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scenario_id');
            $table->dropColumn(['colour', 'icon']);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['colour', 'colour_secondary']);
        });
    }
};
