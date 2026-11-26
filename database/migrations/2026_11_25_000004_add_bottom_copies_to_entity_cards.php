<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many copies of an entity card point their arrow at the bottom half, when
 * its copies do not all point the same way — Tentacle Lash ×3 as two top and
 * one bottom. Null is the whole of "every copy carries the card's one arrow".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_cards', function (Blueprint $table) {
            $table->unsignedSmallInteger('bottom_copies')->nullable()->after('arrow');
        });
    }

    public function down(): void
    {
        Schema::table('entity_cards', function (Blueprint $table) {
            $table->dropColumn('bottom_copies');
        });
    }
};
