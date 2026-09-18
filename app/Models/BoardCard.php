<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'scenario_id', 'name', 'qty', 'health', 'traits', 'text',
        'added_by_beat_id', 'is_placeholder', 'sort',
    ];

    protected $casts = [
        'traits' => 'array',
        'is_placeholder' => 'boolean',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function addedByBeat(): BelongsTo
    {
        return $this->belongsTo(StoryBeat::class, 'added_by_beat_id');
    }
}
