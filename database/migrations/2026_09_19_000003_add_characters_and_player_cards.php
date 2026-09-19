<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Design v3: the player side. A character owns a deck of cards, and a card is
 * one of three roles — kit (starts in play, outside the 40), signature (one of
 * the 20) or upgrade (set aside, swapped in by the Smithy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            // The designer's working names are placeholders; title and story come later.
            $table->string('title')->nullable();
            $table->text('story')->nullable();
            $table->string('status')->nullable();
            $table->text('identity')->nullable();
            $table->unsignedInteger('health')->default(10);
            $table->unsignedInteger('hand_size')->default(5);
            $table->string('ability_name')->nullable();
            $table->text('ability_text')->nullable();
            $table->json('notes')->nullable();
            $table->boolean('is_placeholder')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('player_cards', function (Blueprint $table) {
            $table->id();
            // Null for a domain or neutral card, which belongs to no one character.
            $table->foreignId('character_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->unsignedInteger('qty')->default(1);
            // kit, signature, upgrade — which of the three lists in the design file.
            $table->string('role')->default('signature');
            // signature, domain, neutral — where the card is drawn from when
            // building the 40. Domains are not designed yet.
            $table->string('origin')->default('signature');
            $table->string('domain')->nullable();
            $table->string('type')->default('action'); // action, item, response
            $table->unsignedInteger('gold_cost')->default(0);
            $table->unsignedInteger('omen_icons')->default(0);
            $table->unsignedInteger('shop_cost')->nullable();
            $table->string('start_zone')->default('deck'); // deck, shop, play, upgrade
            $table->text('text')->nullable();
            $table->json('traits')->nullable();
            $table->json('keywords')->nullable();
            // Held as slugs, the way the design files write them, so the export
            // stays verbatim and importing does not depend on card order.
            $table->string('upgrades_to')->nullable();
            $table->string('upgrade_of')->nullable();
            $table->boolean('is_placeholder')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['character_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_cards');
        Schema::dropIfExists('characters');
    }
};
