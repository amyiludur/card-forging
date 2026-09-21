<?php

namespace App\Models;

use App\Support\PlayerScaled;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryBeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'scenario_id', 'order', 'name', 'flavour', 'on_reach', 'advance', 'on_advance', 'dread_change',
        'dread_change_equation',
    ];

    /** What reaching this beat does to Dread: a number, or an equation counting the players. */
    public function dreadChange(): PlayerScaled
    {
        return PlayerScaled::make($this->dread_change, $this->dread_change_equation);
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }
}
