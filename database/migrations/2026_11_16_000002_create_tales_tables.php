<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Tale is a campaign box: scenarios played in order, a story running
     * through them, and cards that only come out when the Tale is played. It
     * lists scenarios — a step names one, it never owns one — and owns the rest:
     * its modes, its log entries, its own cards, reward cards and modules.
     */
    public function up(): void
    {
        Schema::create('tales', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('status')->nullable();
            $table->text('overview')->nullable();
            $table->string('set_icon')->nullable();
            $table->boolean('is_placeholder')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('tale_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->text('intro')->nullable();
            $table->text('outro')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('tale_step_module', function (Blueprint $table) {
            $table->foreignId('tale_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->integer('sort')->default(0);
            $table->primary(['tale_step_id', 'module_id']);
        });

        Schema::create('tale_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tale_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('text')->nullable();
            $table->boolean('is_placeholder')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('tale_log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tale_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind');
            $table->string('scope');
            $table->boolean('is_placeholder')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('tale_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tale_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->integer('qty')->default(1);
            $table->string('type_line')->nullable();
            $table->text('text')->nullable();
            $table->text('flavour')->nullable();
            $table->json('traits')->nullable();
            $table->boolean('is_placeholder')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
            $table->unique(['tale_id', 'slug']);
        });

        // A module or a player card owned by a Tale: the Tale's entity-side
        // cards and its rewards. Null is everything that existed before.
        Schema::table('modules', function (Blueprint $table) {
            $table->foreignId('tale_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('player_cards', function (Blueprint $table) {
            $table->foreignId('tale_id')->nullable()->after('domain_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('player_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tale_id');
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tale_id');
        });

        Schema::dropIfExists('tale_cards');
        Schema::dropIfExists('tale_log_entries');
        Schema::dropIfExists('tale_modes');
        Schema::dropIfExists('tale_step_module');
        Schema::dropIfExists('tale_steps');
        Schema::dropIfExists('tales');
    }
};
