<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which deal a dealt board card belongs to — Townsfolk, say — so a scenario
 * can deal each player one of several kinds of thing. Null unless dealt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_cards', function (Blueprint $table) {
            $table->string('deal_group')->nullable()->after('start');
        });
    }

    public function down(): void
    {
        Schema::table('board_cards', function (Blueprint $table) {
            $table->dropColumn('deal_group');
        });
    }
};
