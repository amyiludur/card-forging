<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domains: the other half of a deck. A deck is 20 signature cards plus 20
 * domain cards, and the domain half is shared rather than owned by one
 * character, the way a module is shared between scenarios.
 *
 * So a player card belongs to a character or to a domain, never both — the
 * same rule entity cards follow for scenarios and modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('status')->nullable();
            $table->text('identity')->nullable();
            // A short badge printed on the card, the way a module marks its own.
            $table->string('set_icon', 16)->nullable();
            // The colourless pool. Its cards fill domain slots without belonging
            // to a colour, which is what neutralFillsDomainSlots is about.
            $table->boolean('is_neutral')->default(false);
            $table->json('notes')->nullable();
            $table->boolean('is_placeholder')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Which domains a character draws its other 20 from. How many a
        // character gets is not decided, so this is a list of any length and
        // the character page reports what it adds up to rather than enforcing.
        Schema::create('character_domain', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);

            $table->unique(['character_id', 'domain_id']);
        });

        Schema::table('player_cards', function (Blueprint $table) {
            // A free-text stand-in from v3 that nothing ever wrote: no design
            // file carries the key and no screen saved it. Real domains replace it.
            $table->dropColumn('domain');
        });

        Schema::table('player_cards', function (Blueprint $table) {
            $table->foreignId('domain_id')->nullable()->after('character_id')
                ->constrained()->cascadeOnDelete();

            // Slugs are unique per owner. SQLite lets a null repeat, which is
            // what makes one index per owner work.
            $table->unique(['domain_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('player_cards', function (Blueprint $table) {
            $table->dropUnique(['domain_id', 'slug']);
            $table->dropConstrainedForeignId('domain_id');
            $table->string('domain')->nullable()->after('origin');
        });

        Schema::dropIfExists('character_domain');
        Schema::dropIfExists('domains');
    }
};
