<?php

namespace App\Support;

use App\Models\EntityCard;
use App\Models\Module;
use App\Models\Scenario;
use Illuminate\Support\Collection;

/**
 * A scenario's base deck plus the modules chosen for this play, and the shape of
 * what comes out. Cards a story beat shuffles in later are kept apart from the
 * starting deck, because only the starting deck is what players face on turn one.
 */
class DeckAssembly
{
    /** @var Collection<int, Module> */
    public Collection $modules;

    public function __construct(public Scenario $scenario, Collection|array $modules)
    {
        $this->modules = collect($modules);
    }

    public static function for(Scenario $scenario, array $moduleSlugs): self
    {
        $modules = Module::with(['entityCards.faces.cardType', 'boardCards'])
            ->whereIn('slug', $moduleSlugs)
            ->orderBy('name')
            ->get();

        return new self($scenario, $modules);
    }

    /** Every card in play this game, base deck and modules together. */
    public function cards(): Collection
    {
        return $this->scenario->entityCards
            ->concat($this->modules->flatMap->entityCards)
            ->values();
    }

    /** The cards shuffled together at setup. */
    public function startingCards(): Collection
    {
        return $this->cards()->filter(fn (EntityCard $c) => $c->added_by_beat_id === null)->values();
    }

    /** Cards a story beat shuffles in as the game goes on. */
    public function beatCards(): Collection
    {
        return $this->cards()->filter(fn (EntityCard $c) => $c->added_by_beat_id !== null)->values();
    }

    /** One entry per printed card, so a quantity of 3 appears three times. */
    public function expand(Collection $cards): Collection
    {
        return $cards->flatMap(fn (EntityCard $c) => array_fill(0, max(1, $c->qty), $c))->values();
    }

    public function modulesRequired(): int
    {
        return $this->scenario->modules_required;
    }

    /** Modules picked that the scenario does not list as compatible. */
    public function incompatible(): Collection
    {
        return $this->modules->reject(fn (Module $m) => $m->worksWith($this->scenario))->values();
    }

    public function countMatchesRules(): bool
    {
        return $this->modules->count() === $this->modulesRequired();
    }

    /** Everything the assembly view reports about the resulting deck. */
    public function stats(): array
    {
        $starting = $this->expand($this->startingCards());
        $beat = $this->expand($this->beatCards());
        $all = $starting->concat($beat);

        return [
            'starting_total' => $starting->count(),
            'beat_total' => $beat->count(),
            'total' => $all->count(),
            'omen_curve' => $this->omenCurve($starting),
            'arrows' => [
                'top' => $starting->where('arrow', 'top')->count(),
                'bottom' => $starting->where('arrow', 'bottom')->count(),
            ],
            'types' => $this->typeCounts($starting),
            'by_source' => $this->bySource(),
        ];
    }

    private function omenCurve(Collection $printed): array
    {
        $buckets = [];

        foreach ($printed as $card) {
            $key = $card->omen_is_x ? 'X' : (string) ($card->omen_cost ?? 0);
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        // PHP turns numeric array keys into ints, so cast back on the way out:
        // the payload should not mix 1 and 'X'.
        $numeric = array_filter(array_keys($buckets), fn ($k) => $k !== 'X');
        sort($numeric);

        $keys = array_merge($numeric, isset($buckets['X']) ? ['X'] : []);

        return array_map(fn ($k) => ['cost' => (string) $k, 'count' => $buckets[$k]], $keys);
    }

    private function typeCounts(Collection $printed): array
    {
        $counts = [];

        foreach ($printed as $card) {
            foreach ($card->faces as $face) {
                $name = $face->cardType?->name ?? 'No type';
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }

        arsort($counts);

        return array_map(fn ($k, $v) => ['type' => $k, 'count' => $v], array_keys($counts), $counts);
    }

    /** How many printed cards each source contributes. */
    private function bySource(): array
    {
        $sources = [[
            'name' => $this->scenario->name,
            'set_icon' => null,
            'kind' => 'scenario',
            'count' => (int) $this->scenario->entityCards->sum('qty'),
        ]];

        foreach ($this->modules as $module) {
            $sources[] = [
                'name' => $module->name,
                'set_icon' => $module->set_icon,
                'kind' => 'module',
                'count' => $module->deckSize(),
            ];
        }

        return $sources;
    }
}
