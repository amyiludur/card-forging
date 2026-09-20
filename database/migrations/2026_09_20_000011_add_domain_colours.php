<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A domain gains the same two colours a character has, printed the same way:
 * a slight gradient across the head band of every card that domain's pool
 * carries. Both nullable, the same as a character's — a domain with no colour
 * keeps the dark blue head a domain card has always printed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('colour', 7)->nullable()->after('identity');
            $table->string('colour_secondary', 7)->nullable()->after('colour');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['colour', 'colour_secondary']);
        });
    }
};
