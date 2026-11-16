<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A module is a themed set of cards dropped into a scenario, in the spirit of
 * Marvel Champions. Its cards belong to it, not to any one scenario.
 */
class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'status', 'theme', 'set_icon',
        'compatible_scenarios', 'traits', 'setup', 'sort', 'tale_id',
    ];

    protected $casts = [
        'compatible_scenarios' => 'array',
        'traits' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Set when the module is a Tale's own: its entity-side cards, which only
     * come out when that Tale is played. Null is a shared module.
     */
    public function tale(): BelongsTo
    {
        return $this->belongsTo(Tale::class);
    }

    public function belongsToTale(): bool
    {
        return $this->tale_id !== null;
    }

    /**
     * Modules for ordinary play. Every list that offers a module to a scenario
     * asks this, so a Tale's own never leaks into a game outside the Tale.
     */
    public function scopeShared(Builder $query): void
    {
        $query->whereNull('tale_id');
    }

    public function entityCards(): HasMany
    {
        return $this->hasMany(EntityCard::class)->orderBy('sort')->orderBy('name');
    }

    public function boardCards(): HasMany
    {
        return $this->hasMany(BoardCard::class)->orderBy('sort');
    }

    /**
     * Districts the module adds to the town when it is chosen. They join the
     * scenario's own; they never replace one.
     */
    public function townActions(): HasMany
    {
        return $this->hasMany(TownAction::class)->orderBy('sort');
    }

    public function deckSize(): int
    {
        return (int) $this->entityCards()->sum('qty');
    }

    /** An empty compatibility list means the module works with every scenario. */
    public function worksWith(Scenario $scenario): bool
    {
        return empty($this->compatible_scenarios)
            || in_array($scenario->slug, $this->compatible_scenarios, true);
    }
}
