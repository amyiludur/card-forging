<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scenario extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'entity_type', 'status', 'overview', 'setup', 'starting_dread',
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

    /**
     * Card types of this scenario's own, on top of the shared library every
     * scenario draws on. Deleting the scenario takes them with it, so a card
     * of another scenario can never be left pointing at one.
     */
    public function cardTypes(): HasMany
    {
        return $this->hasMany(CardType::class)->orderBy('sort')->orderBy('name');
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

    /**
     * The setup card's steps: one per line, as typed.
     *
     * The splitting lives here rather than in either card face, so the print
     * sheet and the browser preview can never disagree about what a step is.
     * A blank line is spacing in the editor, not a step, so it is dropped.
     */
    public function setupSteps(): array
    {
        return array_values(array_filter(
            array_map(trim(...), preg_split('/\r\n|\r|\n/', (string) $this->setup)),
            fn (string $line) => $line !== '',
        ));
    }

    /** A scenario with nothing written prints no setup card, rather than a blank one. */
    public function hasSetup(): bool
    {
        return $this->setupSteps() !== [];
    }

    /** Total printed cards in the entity deck, counting quantities. */
    public function deckSize(): int
    {
        return (int) $this->entityCards()->sum('qty');
    }
}
