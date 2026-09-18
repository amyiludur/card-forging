<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('status')->nullable();
            $table->text('theme')->nullable();
            // Printed in the card corner so decks can be separated after play.
            $table->string('set_icon', 16)->nullable();
            // Empty means the module works with every scenario.
            $table->json('compatible_scenarios')->nullable();
            $table->json('traits')->nullable();
            $table->text('setup')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('scenarios', function (Blueprint $table) {
            $table->unsignedInteger('modules_required')->default(1);
            $table->json('recommended_modules')->nullable();
            $table->text('module_note')->nullable();
        });

        // A card now belongs to a scenario's base deck OR to a module, never both,
        // so scenario_id has to be able to hold null.
        Schema::table('entity_cards', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable()->after('scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scenario_id')->nullable()->change();
        });

        Schema::table('board_cards', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable()->after('scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scenario_id')->nullable()->change();
        });

        // In v2 every entity card carries an arrow on its right edge, pointing at
        // the top or bottom half of the card to its right. Existing rows were
        // null for everything except split cards, so fill them before the column
        // stops accepting null.
        DB::table('entity_cards')->whereNull('arrow')->update(['arrow' => 'top']);

        Schema::table('entity_cards', function (Blueprint $table) {
            $table->string('arrow')->default('top')->nullable(false)->change();
        });

        // SQLite rebuilds the table on change(), which drops the foreign keys it
        // was created with; nothing else in this migration relies on them holding
        // during the rebuild, and the rebuilt table keeps the column definitions.
    }

    public function down(): void
    {
        Schema::table('entity_cards', function (Blueprint $table) {
            $table->string('arrow')->nullable()->change();
        });

        Schema::table('board_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_id');
        });

        Schema::table('entity_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_id');
        });

        DB::table('entity_cards')->whereNull('scenario_id')->delete();
        DB::table('board_cards')->whereNull('scenario_id')->delete();

        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropColumn(['modules_required', 'recommended_modules', 'module_note']);
        });

        Schema::dropIfExists('modules');
    }
};
