<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gold generation moves onto the character card, the way hand size did in v3.
 * The global baseGoldPerRound leaves rules-config.json with it, so there is one
 * place a player reads how much gold they make: their own card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // 2 was the global base, so an existing character keeps generating
            // what it generated before this column existed.
            $table->unsignedInteger('gold_per_round')->default(2)->after('hand_size');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('gold_per_round');
        });
    }
};
