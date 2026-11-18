<?php

namespace App\Models;

use App\Models\Concerns\HasArt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TownAction extends Model
{
    use HasArt, HasFactory;

    protected $fillable = ['scenario_id', 'module_id', 'name', 'effect', 'gold_cost', 'omen', 'note', 'flavour', 'sort'];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    /** Set instead of the scenario when a module brings the district. */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
