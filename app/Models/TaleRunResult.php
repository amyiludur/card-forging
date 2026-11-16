<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one scenario of a playthrough left in the log. `values` is keyed by log
 * entry id: a party entry holds its value, a character entry holds one value
 * per character slug. Recording the scenario again replaces it whole.
 */
class TaleRunResult extends Model
{
    protected $fillable = ['tale_run_id', 'tale_step_id', 'values', 'played_at'];

    protected $casts = [
        'values' => 'array',
        'played_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(TaleRun::class, 'tale_run_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(TaleStep::class, 'tale_step_id');
    }
}
