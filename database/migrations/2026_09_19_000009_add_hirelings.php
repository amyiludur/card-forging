<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Design v3.1: the Hireling, a fourth player card type.
 *
 * A Hireling is played like any other card and then stays in play with a
 * number of uses and a sacrifice value: each activation spends a use, at zero
 * uses it returns to the shop, and sacrificing it prevents damage. Both
 * numbers only mean anything on a Hireling, so both are nullable and a card of
 * any other type leaves them alone.
 *
 * The rules behind them are placeholders in the designer's brief, so nothing
 * here enforces them — the columns hold what the design file says and the app
 * reports what does not line up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_cards', function (Blueprint $table) {
            // How many activations the Hireling has before its term ends.
            $table->unsignedInteger('uses')->nullable()->after('omen_icons');
            // The damage sending it away prevents.
            $table->unsignedInteger('sacrifice_value')->nullable()->after('uses');
        });
    }

    public function down(): void
    {
        Schema::table('player_cards', function (Blueprint $table) {
            $table->dropColumn(['uses', 'sacrifice_value']);
        });
    }
};
