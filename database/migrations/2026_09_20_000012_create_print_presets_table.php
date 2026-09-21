<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A saved print setup — card size, sheet size, margins, the sticker grid, all
 * of it — under a name, so lining a sticker sheet up once does not have to be
 * redone (or bookmarked) for every scenario, character and domain printed on
 * it. Every print options page offers the same list; saving under a name
 * already in use overwrites it, the same way printing a URL you have already
 * bookmarked just updates what that bookmark points at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('options');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_presets');
    }
};
