<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A district now belongs to a scenario's town OR to a module, never both —
     * the same rule entity and board cards follow — so a module can bring a
     * district of its own into the town when it is chosen.
     */
    public function up(): void
    {
        Schema::table('town_actions', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable()->after('scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scenario_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('town_actions')->whereNull('scenario_id')->delete();

        Schema::table('town_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_id');
            $table->foreignId('scenario_id')->nullable(false)->change();
        });
    }
};
