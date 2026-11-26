<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a setup board card starts: on the board, as every one did before, or
 * dealt to the players — one each at random, the rest kept aside — the way
 * the Meddler in the Dark hands out its townsfolk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_cards', function (Blueprint $table) {
            $table->string('start')->default('board')->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('board_cards', function (Blueprint $table) {
            $table->dropColumn('start');
        });
    }
};
