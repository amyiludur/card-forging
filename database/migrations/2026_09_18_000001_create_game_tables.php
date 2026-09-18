<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rules_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('group')->default('general');
            $table->string('value_type')->default('int'); // int, bool, string, range
            $table->json('value')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_placeholder')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('card_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('entity_type')->default('creature'); // creature, concept, group
            $table->string('status')->nullable();
            $table->text('overview')->nullable();
            $table->unsignedInteger('starting_dread')->default(2);
            $table->text('dread_effect')->nullable();
            $table->json('traits')->nullable();
            $table->text('win_text')->nullable();
            $table->text('lose_text')->nullable();
            // Designer decision: arrows are printed on split cards and overridden
            // in play by a token. Kept per scenario so it can be revisited.
            $table->boolean('printed_arrows')->default(true);
            $table->timestamps();
        });

        Schema::create('story_beats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->string('name');
            $table->text('flavour')->nullable();
            $table->text('on_reach')->nullable();
            $table->text('advance')->nullable();
            $table->text('on_advance')->nullable();
            $table->integer('dread_change')->default(0);
            $table->timestamps();

            $table->unique(['scenario_id', 'order']);
        });

        Schema::create('entity_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('qty')->default(1);
            $table->string('layout')->default('single'); // single, split, x-cost
            $table->unsignedInteger('omen_cost')->nullable(); // null when the cost is X
            $table->boolean('omen_is_x')->default(false);
            $table->json('traits')->nullable();
            $table->foreignId('added_by_beat_id')->nullable()->constrained('story_beats')->nullOnDelete();
            $table->string('arrow')->nullable(); // top or bottom, split cards only
            $table->text('notes')->nullable();
            $table->boolean('is_placeholder')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('entity_card_faces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_card_id')->constrained()->cascadeOnDelete();
            $table->string('half')->default('single'); // single, top, bottom
            $table->foreignId('card_type_id')->nullable()->constrained()->nullOnDelete();
            $table->text('text')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('board_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('qty')->default(1);
            $table->string('health')->nullable(); // free text: "3", "12 per player", null
            $table->json('traits')->nullable();
            $table->text('text')->nullable();
            $table->foreignId('added_by_beat_id')->nullable()->constrained('story_beats')->nullOnDelete();
            $table->boolean('is_placeholder')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('town_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('effect')->nullable();
            $table->unsignedInteger('gold_cost')->nullable();
            $table->unsignedInteger('omen')->default(1);
            $table->text('note')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('rule_documents', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('rule_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_document_id')->constrained()->cascadeOnDelete();
            $table->longText('body')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_document_versions');
        Schema::dropIfExists('rule_documents');
        Schema::dropIfExists('town_actions');
        Schema::dropIfExists('board_cards');
        Schema::dropIfExists('entity_card_faces');
        Schema::dropIfExists('entity_cards');
        Schema::dropIfExists('story_beats');
        Schema::dropIfExists('scenarios');
        Schema::dropIfExists('card_types');
        Schema::dropIfExists('rules_configs');
    }
};
