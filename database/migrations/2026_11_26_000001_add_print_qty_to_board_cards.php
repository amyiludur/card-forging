<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many copies of a board card the print sheet needs, apart from how many
 * go out at setup. Null is the whole of "print what setup puts out", which is
 * what every board card did before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_cards', function (Blueprint $table) {
            $table->unsignedSmallInteger('print_qty')->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('board_cards', function (Blueprint $table) {
            $table->dropColumn('print_qty');
        });
    }
};
