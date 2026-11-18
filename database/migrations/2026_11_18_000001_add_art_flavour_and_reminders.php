<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card art, flavour and keyword reminders.
 *
 * Every card kind with an editor can carry a picture: the path into design/art,
 * the window's size, crop-or-fit, and the point kept in view when cropping.
 * Null is "default" for all four — App\Support\CardArt is where the defaults
 * live. Flavour joins the four kinds that had none, and a keyword gains a short
 * reminder for the print sheet.
 */
return new class extends Migration
{
    private const ART_TABLES = [
        'entity_cards', 'board_cards', 'town_actions', 'player_cards', 'characters', 'story_beats', 'tale_cards',
    ];

    private const FLAVOUR_TABLES = ['entity_cards', 'board_cards', 'town_actions', 'player_cards'];

    public function up(): void
    {
        foreach (self::ART_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('art')->nullable();
                $table->unsignedTinyInteger('art_size')->nullable();
                $table->string('art_fit', 10)->nullable();
                $table->json('art_focus')->nullable();
            });
        }

        foreach (self::FLAVOUR_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->text('flavour')->nullable();
            });
        }

        Schema::table('keywords', function (Blueprint $table) {
            $table->text('reminder')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('keywords', fn (Blueprint $table) => $table->dropColumn('reminder'));

        foreach (self::FLAVOUR_TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('flavour'));
        }

        foreach (self::ART_TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['art', 'art_size', 'art_fit', 'art_focus']));
        }
    }
};
