<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abilities a character card carries beyond its identity ability.
 *
 * A list of {name, text} pairs, printed under the first one. Null is the whole
 * of "this character has one ability", which is every character so far, so a
 * design folder nobody has given a second ability comes back out byte for byte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->json('extra_abilities')->nullable()->after('ability_text');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('extra_abilities');
        });
    }
};
