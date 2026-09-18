<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TownAction extends Model
{
    use HasFactory;

    protected $fillable = ['scenario_id', 'name', 'effect', 'gold_cost', 'omen', 'note', 'sort'];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }
}
