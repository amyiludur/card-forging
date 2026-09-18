<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryBeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'scenario_id', 'order', 'name', 'flavour', 'on_reach', 'advance', 'on_advance', 'dread_change',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }
}
