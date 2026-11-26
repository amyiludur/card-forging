<?php

namespace App\Models;

use App\Support\PlayerScaled;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Scenario extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'entity_type', 'status', 'overview', 'setup', 'starting_dread',
        'starting_dread_equation', 'starting_omen', 'starting_omen_equation', 'dread_effect', 'traits', 'win_text', 'lose_text', 'printed_arrows',
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
        return Module::shared()->orderBy('name')->get()->filter(fn (Module $m) => $m->worksWith($this))->values();
    }

    public function boardCards(): HasMany
    {
        return $this->hasMany(BoardCard::class)->orderBy('sort');
    }

    public function townActions(): HasMany
    {
        return $this->hasMany(TownAction::class)->orderBy('sort');
    }

    /** The common districts this scenario's town uses, and the line it adds to each. */
    public function townPicks(): HasMany
    {
        return $this->hasMany(ScenarioTownPick::class)->orderBy('sort');
    }

    /**
     * The town as it is played and printed: this scenario's own districts and
     * the common ones it picked, in one order. The one place a town is built,
     * so the Town tab, the print run, the pocket page and the table cannot
     * disagree about what is in it. Every district carries this scenario, so
     * {dreadRule} on a common district is the picking scenario's rule.
     *
     * @return Collection<int, TownAction>
     */
    public function town(): Collection
    {
        $this->loadMissing(['townActions', 'townPicks.townAction']);

        $own = $this->townActions->map(fn (TownAction $a) => [$a->sort, 0, $a->setRelation('scenario', $this)]);
        $picked = $this->townPicks
            ->filter(fn (ScenarioTownPick $p) => $p->townAction !== null)
            ->map(fn (ScenarioTownPick $p) => [$p->sort, 1, $p->district($this)]);

        // By sort, and an own district before a pick that shares its number.
        return $own->concat($picked)
            ->sort(fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]])
            ->map(fn ($row) => $row[2])
            ->values()
            ->toBase();
    }

    /** The next free place at the end of the town, for a new district or a new pick. */
    public function nextTownSort(): int
    {
        return max((int) $this->townActions()->max('sort'), (int) $this->townPicks()->max('sort')) + 1;
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

    /**
     * The Dread the dial starts on: a plain number, or the designer's equation
     * counting the players. The setup card prints whichever it is, because a
     * printed card cannot know how many people are at the table.
     */
    public function startingDread(): PlayerScaled
    {
        return PlayerScaled::make($this->starting_dread, $this->starting_dread_equation);
    }

    /** True when this scenario sets its own starting omen rather than leaving it to the tunable number. */
    public function hasOwnStartingOmen(): bool
    {
        return $this->starting_omen !== null || trim((string) $this->starting_omen_equation) !== '';
    }

    /**
     * The omen in the pool at setup: this scenario's own number or equation
     * when it has one, and the shared `startingOmen` tunable number otherwise.
     *
     * @param  PlayerScaled|null  $shared  the tunable number, when the caller
     *                                     already has it — a page of many cards
     *                                     reads it once rather than per card
     */
    public function startingOmen(?PlayerScaled $shared = null): PlayerScaled
    {
        return $this->hasOwnStartingOmen()
            ? PlayerScaled::make($this->starting_omen, $this->starting_omen_equation)
            : ($shared ?? self::sharedStartingOmen());
    }

    /**
     * The `startingOmen` tunable number, which every scenario without its own
     * falls back to. Empty when the rules config has none, so a card quoting it
     * reports the gap rather than printing a 0 nobody wrote.
     */
    public static function sharedStartingOmen(): PlayerScaled
    {
        $value = RulesConfig::where('key', 'startingOmen')->first()?->raw_value;

        return $value === null || $value === ''
            ? PlayerScaled::make(null)
            : PlayerScaled::fromDesign($value, 0);
    }

    /** Total printed cards in the entity deck, counting quantities. */
    public function deckSize(): int
    {
        return (int) $this->entityCards()->sum('qty');
    }
}
