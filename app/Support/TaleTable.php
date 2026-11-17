<?php

namespace App\Support;

use App\Models\PlayerCard;
use App\Models\Scenario;
use App\Models\TaleLogEntry;
use App\Models\TaleRun;
use App\Models\TaleStep;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The playtest table playing one scenario of a playthrough. `?run=` names the
 * run; the step played is the run's next one, and only when it is this
 * scenario's — a run opened on the wrong scenario is ignored and named, never
 * recorded against a step that is not being played.
 *
 * What the table takes from the log is the earned rewards, dealt into each
 * character's deck. Everything else is shown, and the players apply it.
 */
class TaleTable
{
    private ?TaleStep $next;

    private function __construct(private TaleRun $run, private Scenario $scenario)
    {
        $this->run->loadMissing(['tale.steps.scenario', 'tale.logEntries', 'mode', 'results']);
        $this->next = $run->nextStep();
    }

    public static function for(Request $request, Scenario $scenario): ?self
    {
        $run = $request->filled('run') ? TaleRun::find((int) $request->input('run')) : null;

        return $run ? new self($run, $scenario) : null;
    }

    /** The step being played: the run's next, when it is this scenario's. */
    public function step(): ?TaleStep
    {
        return $this->next !== null && $this->next->scenario_id === $this->scenario->id ? $this->next : null;
    }

    public function notice(): ?string
    {
        if ($this->next === null) {
            return 'This playthrough is finished.';
        }

        if ($this->step() === null) {
            $next = $this->next->scenario?->name ?? 'another scenario';

            return "This playthrough's next scenario is {$next}, not {$this->scenario->name}. Playing {$this->scenario->name} on its own.";
        }

        return null;
    }

    /** @return array<int, string> the step's modules, in its own order */
    public function moduleSlugs(): array
    {
        return $this->step()?->modules()->pluck('slug')->all() ?? [];
    }

    /** @return array<int, int> what this character has earned, when a step is being played */
    public function rewardIdsFor(string $characterSlug): array
    {
        return $this->step() ? $this->run->rewardIdsFor($characterSlug) : [];
    }

    /** @return Collection<int, PlayerCard> every reward the Tale holds */
    public function rewardCards(): Collection
    {
        return $this->run->tale->rewardCards()->with('tale')->where('role', PlayerCard::ROLE_REWARD)->get();
    }

    /** What the table's setup screen and the Record results form read. */
    public function props(CardPresenter $presenter): ?array
    {
        $step = $this->step();

        if ($step === null) {
            return null;
        }

        $tale = $this->run->tale;
        $number = $tale->steps->search(fn (TaleStep $s) => $s->is($step)) + 1;
        $markup = Markup::make();
        $mode = $this->run->mode;

        return [
            'run' => ['id' => $this->run->id, 'name' => $this->run->name],
            'tale' => ['slug' => $tale->slug, 'name' => $tale->name],
            'mode' => $mode ? [
                'name' => $mode->name,
                'text_html' => ($mode->text ?? '') === '' ? '' : (new RulesMarkdown($markup))->toHtml($mode->text, $tale->slug.'-mode'),
            ] : null,
            'step' => ['id' => $step->id] + TaleBook::for($tale)->step($step, $number) + [
                // What this step already recorded, for the form to start from.
                'values' => $this->run->resultFor($step)?->values ?? [],
            ],
            'grid' => $this->run->logGrid(),
            'entries' => $tale->logEntries->map(fn (TaleLogEntry $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'name_html' => $markup->toHtml($e->name),
                'kind' => $e->kind,
                'scope' => $e->scope,
            ])->values()->all(),
            'rewardCards' => $this->rewardCards()->map(fn (PlayerCard $c) => $presenter->playerCard($c))->values()->all(),
            'partyRewards' => $this->run->partyRewardIds(),
        ];
    }
}
