<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scenario extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'entity_type', 'status', 'overview', 'starting_dread',
        'dread_effect', 'traits', 'win_text', 'lose_text', 'printed_arrows',
        'modules_required', 'recommended_modules', 'module_note',
    ];

    protected $casts = [
        'traits' => 'array',
        'recommended_modules' => 'array',
        'printed_arrows' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function storyBeats(): HasMany
    {
        return $this->hasMany(StoryBeat::class)->orderBy('order');
    }

    public function entityCards(): HasMany
    {
        return $this->hasMany(EntityCard::class)->orderBy('sort')->orderBy('name');
    }

    /** Modules this scenario can be played with. */
    public function compatibleModules()
    {
        return Module::orderBy('name')->get()->filter(fn (Module $m) => $m->worksWith($this))->values();
    }

    public function boardCards(): HasMany
    {
        return $this->hasMany(BoardCard::class)->orderBy('sort');
    }

    public function townActions(): HasMany
    {
        return $this->hasMany(TownAction::class)->orderBy('sort');
    }

    /** Total printed cards in the entity deck, counting quantities. */
    public function deckSize(): int
    {
        return (int) $this->entityCards()->sum('qty');
    }
}
