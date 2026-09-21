<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The keyword library: the designer's own {token}s.
 *
 * The five icon tokens in Markup::ICONS are the game's core symbols and stay in
 * code. Everything else a card might want to say in one word — Unique, Fired,
 * Bottom draw — is data, so the designer can add one without a release.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            // What goes inside the braces: {unique}.
            $table->string('token')->unique();
            $table->string('name');
            // An App\Support\Icons name drawn before the name, if any.
            $table->string('icon')->nullable();
            // False prints the icon alone, for a keyword that is a symbol.
            $table->boolean('show_name')->default(true);
            // What toPlain() writes, so a design-folder diff reads as text.
            // Null means the name.
            $table->string('plain')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_placeholder')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keywords');
    }
};
