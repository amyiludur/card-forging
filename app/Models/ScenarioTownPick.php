<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A common district a scenario's town uses, and the line the scenario adds to
 * it. The district is never copied: editing the Smithy changes every town that
 * picked it, which is the point of writing it once.
 */
class ScenarioTownPick extends Model
{
    protected $fillable = ['scenario_id', 'town_action_id', 'extra', 'sort'];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function townAction(): BelongsTo
    {
        return $this->belongsTo(TownAction::class);
    }

    /**
     * The district as it stands in this scenario's town: a copy of the common
     * row carrying this scenario (so its Dread tokens are this scenario's) and
     * this pick (so the presenter adds the extra line). A copy, because the
     * same district in two towns must not share either relation.
     */
    public function district(?Scenario $scenario = null): TownAction
    {
        $district = clone $this->townAction;
        $district->setRelation('scenario', $scenario ?? $this->scenario);
        $district->setRelation('pick', $this);

        return $district;
    }
}
