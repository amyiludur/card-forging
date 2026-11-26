<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A district with no scenario and no module is a common one, written once.
     * A scenario picks the common districts its town uses, and a pick may add
     * a line of the scenario's own. `sort` shares one ordering with the
     * scenario's own districts, so a town is the two merged.
     */
    public function up(): void
    {
        Schema::create('scenario_town_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('town_action_id')->constrained()->cascadeOnDelete();
            $table->text('extra')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['scenario_id', 'town_action_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_town_picks');
    }
};
