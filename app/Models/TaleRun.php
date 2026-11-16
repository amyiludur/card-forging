<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One playthrough of a Tale: a name, the mode it is played in, and what each
 * scenario left in the log. A fact about the designer's playtests, like a
 * saved deck, so design:export knows nothing about it.
 *
 * The next scenario is worked out, never stored: the first step, in play
 * order, with no result. So adding, removing or reordering the Tale's steps
 * after a run starts cannot leave it pointing at the wrong one.
 */
class TaleRun extends Model
{
    protected $fillable = ['tale_id', 'name', 'tale_mode_id'];

    public function tale(): BelongsTo
    {
        return $this->belongsTo(Tale::class);
    }

    public function mode(): BelongsTo
    {
        return $this->belongsTo(TaleMode::class, 'tale_mode_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(TaleRunResult::class);
    }

    public function nextStep(): ?TaleStep
    {
        $recorded = $this->results()->pluck('tale_step_id')->all();

        return $this->tale->steps()->whereNotIn('id', $recorded)->first();
    }

    /** Every scenario recorded. A Tale with no scenarios is not finished; it is empty. */
    public function isFinished(): bool
    {
        return $this->tale->steps()->exists() && $this->nextStep() === null;
    }

    public function resultFor(TaleStep $step): ?TaleRunResult
    {
        return $this->results()->where('tale_step_id', $step->id)->first();
    }

    /**
     * Every reward recorded against one character, in step order, each once —
     * what the table deals into that character's deck.
     *
     * @return array<int, int>
     */
    public function rewardIdsFor(string $characterSlug): array
    {
        return $this->rewardIds('character', fn ($value) => $value[$characterSlug] ?? []);
    }

    /**
     * Rewards recorded for the whole party. Never dealt: they have no one deck
     * to join, so the table lists them and the players put them where they go.
     *
     * @return array<int, int>
     */
    public function partyRewardIds(): array
    {
        return $this->rewardIds('party', fn ($value) => $value);
    }

    /**
     * Every character slug any result names, first seen first.
     *
     * @return array<int, string>
     */
    public function characters(): array
    {
        $characterEntries = $this->entries()->where('scope', 'character')->pluck('id');
        $slugs = [];

        foreach ($this->orderedResults() as $result) {
            foreach ($characterEntries as $id) {
                foreach (array_keys((array) ($result->values[$id] ?? [])) as $slug) {
                    $slugs[$slug] = true;
                }
            }
        }

        return array_keys($slugs);
    }

    /**
     * The log as the printed sheet lays it out: a column per scenario, a row
     * per party entry, then a block per character with a row per character
     * entry. Each cell is what was written, as text.
     */
    public function logGrid(): array
    {
        $steps = $this->tale->steps()->with('scenario')->get()->values();
        $results = $this->results->keyBy('tale_step_id');
        $entries = $this->entries();
        $rewardNames = PlayerCard::where('tale_id', $this->tale_id)->pluck('name', 'id');
        $characterNames = Character::pluck('name', 'slug');

        $cells = fn (TaleLogEntry $entry, ?string $slug) => $steps->map(function (TaleStep $step) use ($entry, $slug, $results, $rewardNames) {
            $result = $results->get($step->id);

            if ($result === null) {
                return '';
            }

            $value = $result->values[$entry->id] ?? null;

            if ($slug !== null) {
                $value = is_array($value) ? ($value[$slug] ?? null) : null;
            }

            return $this->display($entry->kind, $value, $rewardNames);
        })->all();

        $row = fn (TaleLogEntry $entry, ?string $slug = null) => [
            'entry_id' => $entry->id,
            'name' => $entry->name,
            'kind' => $entry->kind,
            'cells' => $cells($entry, $slug),
        ];

        return [
            'steps' => $steps->map(fn (TaleStep $step, int $i) => [
                'id' => $step->id,
                'number' => $i + 1,
                'scenario' => $step->scenario?->name ?? 'Missing scenario',
                'recorded' => $results->has($step->id),
            ])->all(),
            'party' => $entries->where('scope', 'party')->map(fn ($entry) => $row($entry))->values()->all(),
            'characters' => collect($this->characters())->map(fn (string $slug) => [
                'slug' => $slug,
                'name' => $characterNames[$slug] ?? $slug,
                'rows' => $entries->where('scope', 'character')->map(fn ($entry) => $row($entry, $slug))->values()->all(),
            ])->all(),
        ];
    }

    /** One cell, as the log sheet would have it written in. */
    private function display(string $kind, mixed $value, Collection $rewardNames): string
    {
        return match ($kind) {
            'check' => $value ? '✓' : '—',
            'rewards' => collect((array) $value)->map(fn ($id) => $rewardNames[$id] ?? "#{$id}")->implode(', '),
            default => $value === null ? '' : (string) $value,
        };
    }

    private function entries(): Collection
    {
        return $this->tale->logEntries()->get();
    }

    private function orderedResults(): Collection
    {
        $order = $this->tale->steps()->pluck('id')->flip();

        return $this->results()->get()->sortBy(fn (TaleRunResult $r) => $order[$r->tale_step_id] ?? PHP_INT_MAX)->values();
    }

    /**
     * Reward ids from the `rewards` entries of one scope, in step order, each once.
     *
     * @return array<int, int>
     */
    private function rewardIds(string $scope, callable $pick): array
    {
        $entries = $this->entries()->where('kind', 'rewards')->where('scope', $scope)->pluck('id');
        $ids = [];

        foreach ($this->orderedResults() as $result) {
            foreach ($entries as $id) {
                foreach ((array) $pick($result->values[$id] ?? []) as $reward) {
                    $ids[(int) $reward] = true;
                }
            }
        }

        return array_keys($ids);
    }
}
