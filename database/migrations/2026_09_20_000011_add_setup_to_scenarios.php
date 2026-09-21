<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The setup card: how a scenario is laid out before the first round.
 *
 * Every scenario's markdown already has a `## Setup` section, and none of it
 * reached the table — the board cards, the beats and the deck all print, but
 * nothing says what to do with them. This is that sentence, and it is the
 * designer's to write: one step per line, printed numbered on a card of its
 * own. Nullable, because a scenario whose setup is not written yet prints no
 * setup card rather than a made-up one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->text('setup')->nullable()->after('overview');
        });
    }

    public function down(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropColumn('setup');
        });
    }
};
