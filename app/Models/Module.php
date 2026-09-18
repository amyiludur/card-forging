<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'compatible_scenarios', 'traits', 'setup', 'sort',
    ];

    protected $casts = [
        'compatible_scenarios' => 'array',
        'traits' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function entityCards(): HasMany
    {
        return $this->hasMany(EntityCard::class)->orderBy('sort')->orderBy('name');
    }

    public function boardCards(): HasMany
    {
        return $this->hasMany(BoardCard::class)->orderBy('sort');
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
