<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One scenario in a Tale, in play order. It names the scenario rather than
 * owning it, and says which modules join it — the Tale's own or shared ones —
 * plus the story read before and after it.
 */
class TaleStep extends Model
{
    protected $fillable = ['tale_id', 'scenario_id', 'intro', 'outro', 'notes', 'sort'];

    public function tale(): BelongsTo
    {
        return $this->belongsTo(Tale::class);
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'tale_step_module')
            ->withPivot('sort')
            ->orderByPivot('sort');
    }
}
