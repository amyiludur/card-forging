<?php

namespace App\Models;

use App\Models\Concerns\HasArt;
use App\Models\Concerns\NamedByCards;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TownAction extends Model
{
    use HasArt, HasFactory, NamedByCards;

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

    /** The towns a common district is in. Deleting the district takes these with it. */
    public function picks(): HasMany
    {
        return $this->hasMany(ScenarioTownPick::class);
    }

    /** Districts written once, owned by no scenario and no module, for any scenario to pick. */
    public function scopeCommon(Builder $query): Builder
    {
        return $query->whereNull('scenario_id')->whereNull('module_id');
    }

    public function isCommon(): bool
    {
        return $this->scenario_id === null && $this->module_id === null;
    }
}
