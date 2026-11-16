<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A scenario's own starting omen: the omen in the pool at setup.
 *
 * Both columns are nullable, and both null is the whole of "this scenario
 * leaves it to the tunable number": `startingOmen` in the rules config still
 * applies to any scenario that does not say otherwise. The equation sits
 * beside the number exactly as `starting_dread_equation` does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->unsignedInteger('starting_omen')->nullable()->after('starting_dread_equation');
            $table->string('starting_omen_equation')->nullable()->after('starting_omen');
        });
    }

    public function down(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropColumn(['starting_omen', 'starting_omen_equation']);
        });
    }
};
