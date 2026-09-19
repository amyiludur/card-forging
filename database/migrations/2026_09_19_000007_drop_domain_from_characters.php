<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A character does not have a domain. Characters and domains are two separate
 * things, and the place they meet is deck building: pick a character, pick a
 * domain, take 20 of its cards. So the choice belongs to a deck being built,
 * not to the character row, and `/decks` is where it is made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('domain_id');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->foreignId('domain_id')->nullable()->after('slug')
                ->constrained()->nullOnDelete();
        });
    }
};
