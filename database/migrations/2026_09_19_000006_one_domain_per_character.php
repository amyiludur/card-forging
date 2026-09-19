<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The designer settled it: a character takes one domain, any domain, and
 * chooses 20 cards out of it when building a deck. So the pivot that allowed
 * any number becomes a single column, and a pool is no longer meant to be 20
 * cards — it is meant to be able to supply 20.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // Losing a domain leaves the character standing, with none chosen.
            $table->foreignId('domain_id')->nullable()->after('slug')
                ->constrained()->nullOnDelete();
        });

        // Whatever the pivot held, the first one it listed is the one kept.
        foreach (DB::table('character_domain')->orderBy('sort')->orderBy('id')->get() as $row) {
            DB::table('characters')
                ->where('id', $row->character_id)
                ->whereNull('domain_id')
                ->update(['domain_id' => $row->domain_id]);
        }

        Schema::dropIfExists('character_domain');
    }

    public function down(): void
    {
        Schema::create('character_domain', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);

            $table->unique(['character_id', 'domain_id']);
        });

        foreach (DB::table('characters')->whereNotNull('domain_id')->get() as $character) {
            DB::table('character_domain')->insert([
                'character_id' => $character->id,
                'domain_id' => $character->domain_id,
                'sort' => 0,
            ]);
        }

        Schema::table('characters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('domain_id');
        });
    }
};
