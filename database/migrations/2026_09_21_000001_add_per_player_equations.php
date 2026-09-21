<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The numbers the designer can write as an equation counting the players.
 *
 * Each one keeps the integer column it already had and gains a nullable
 * equation beside it. Null is the whole of "this is a plain number", which is
 * what every one of them was before and still is by default — the same shape a
 * colour, a Hireling's two numbers and a scenario's setup follow.
 *
 * The number is not thrown away when an equation is written: it is what the
 * editor puts back when the toggle goes off, and what the value falls back to
 * when no equation is there. The equation wins while it exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->string('starting_dread_equation')->nullable()->after('starting_dread');
        });

        Schema::table('story_beats', function (Blueprint $table) {
            $table->string('dread_change_equation')->nullable()->after('dread_change');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->string('health_equation')->nullable()->after('health');
            $table->string('hand_size_equation')->nullable()->after('hand_size');
            $table->string('gold_per_round_equation')->nullable()->after('gold_per_round');
        });
    }

    public function down(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropColumn('starting_dread_equation');
        });

        Schema::table('story_beats', function (Blueprint $table) {
            $table->dropColumn('dread_change_equation');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['health_equation', 'hand_size_equation', 'gold_per_round_equation']);
        });
    }
};
