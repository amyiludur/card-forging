<?php

namespace Tests\Concerns;

use App\Models\PlayerCard;
use App\Models\Scenario;
use App\Models\Tale;
use App\Models\TaleLogEntry;
use App\Models\TaleRun;
use App\Models\TaleStep;

/**
 * One Tale to play through, the same in every playthrough test: the Kraken
 * then the Dead Legion, two modes, an entry of every kind and both scopes, and
 * two reward cards. Read it as data — the keys name what each test touches.
 */
trait MakesTaleRuns
{
    protected Tale $tale;

    /** @var array<string, TaleStep> */
    protected array $steps = [];

    /** @var array<string, \App\Models\TaleMode> */
    protected array $modes = [];

    /** @var array<string, TaleLogEntry> keyed ship, lighthouse, kept, earned, spoils */
    protected array $entries = [];

    /** @var array<string, PlayerCard> keyed charm, compass */
    protected array $rewards = [];

    protected function makeTale(): void
    {
        $this->tale = Tale::create(['slug' => 'drowned-coast', 'name' => 'The Drowned Coast']);

        foreach (['kraken', 'march-of-dead-legion'] as $sort => $slug) {
            $this->steps[$slug] = $this->tale->steps()->create([
                'scenario_id' => Scenario::where('slug', $slug)->value('id'),
                'sort' => $sort,
            ]);
        }

        foreach (['story' => 'Story', 'hard' => 'Hard'] as $key => $name) {
            $this->modes[$key] = $this->tale->modes()->create(['name' => $name, 'sort' => count($this->modes)]);
        }

        $entries = [
            'ship' => ['Ship damage', 'number', 'party'],
            'lighthouse' => ['Lighthouse fell', 'check', 'party'],
            'kept' => ['Damage kept', 'number', 'character'],
            'earned' => ['Earned', 'rewards', 'character'],
            'spoils' => ['Shared spoils', 'rewards', 'party'],
        ];

        foreach ($entries as $key => [$name, $kind, $scope]) {
            $this->entries[$key] = $this->tale->logEntries()->create([
                'name' => $name, 'kind' => $kind, 'scope' => $scope, 'sort' => count($this->entries),
            ]);
        }

        foreach (['charm' => 'Sea-glass Charm', 'compass' => 'Old Compass'] as $key => $name) {
            $this->rewards[$key] = PlayerCard::create([
                'tale_id' => $this->tale->id, 'slug' => $key, 'name' => $name,
                'role' => 'reward', 'origin' => 'reward', 'type' => 'item', 'start_zone' => 'deck',
            ]);
        }
    }

    /** Not run(): that is PHPUnit's own, and final. */
    protected function startRun(string $name = 'First try', ?string $mode = null): TaleRun
    {
        return $this->tale->runs()->create([
            'name' => $name,
            'tale_mode_id' => $mode ? $this->modes[$mode]->id : null,
        ]);
    }

    /**
     * Record a step, with values keyed by the fixture's entry keys rather than
     * by id: ['ship' => 3, 'kept' => ['gunslinger' => 4]].
     */
    protected function record(TaleRun $run, string $step, array $values): void
    {
        $run->results()->updateOrCreate(
            ['tale_step_id' => $this->steps[$step]->id],
            ['values' => $this->byId($values), 'played_at' => now()],
        );
    }

    /** The same values keyed by entry id, as a request sends them. */
    protected function byId(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            $out[(string) $this->entries[$key]->id] = $value;
        }

        return $out;
    }
}
