<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntityCard extends Model
{
    use HasFactory;

    public const LAYOUTS = ['single', 'split', 'x-cost'];

    protected $fillable = [
        'scenario_id', 'module_id', 'name', 'qty', 'layout', 'omen_cost', 'omen_is_x',
        'traits', 'added_by_beat_id', 'arrow', 'notes', 'is_placeholder', 'sort',
    ];

    protected $casts = [
        'traits' => 'array',
        'omen_is_x' => 'boolean',
        'is_placeholder' => 'boolean',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** Where this card came from, for the set icon and the card list. */
    public function origin(): ?string
    {
        return $this->module?->name ?? $this->scenario?->name;
    }

    public function faces(): HasMany
    {
        return $this->hasMany(EntityCardFace::class)->orderBy('sort');
    }

    public function addedByBeat(): BelongsTo
    {
        return $this->belongsTo(StoryBeat::class, 'added_by_beat_id');
    }

    public function getOmenLabelAttribute(): string
    {
        return $this->omen_is_x ? 'X' : (string) ($this->omen_cost ?? 0);
    }

    public function isSplit(): bool
    {
        return $this->layout === 'split';
    }

    /**
     * The arrow sits on the right edge and points at the top or bottom half of
     * the card to its right, so it says nothing about this card's own halves.
     */
    public function pointsAt(): string
    {
        return $this->arrow ?: 'top';
    }
}
