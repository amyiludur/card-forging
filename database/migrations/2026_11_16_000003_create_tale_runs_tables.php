<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A playthrough of a Tale, and one result per scenario played. A fact about
     * the designer's playtests, like a saved deck — never written to design/.
     * There is no "current step" column: the next step is the first one with
     * no result, so it survives the Tale's steps changing after a run starts.
     */
    public function up(): void
    {
        Schema::create('tale_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tale_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('tale_mode_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tale_run_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tale_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tale_step_id')->constrained()->cascadeOnDelete();
            $table->json('values');
            $table->timestamp('played_at')->nullable();
            $table->timestamps();
            $table->unique(['tale_run_id', 'tale_step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tale_run_results');
        Schema::dropIfExists('tale_runs');
    }
};
