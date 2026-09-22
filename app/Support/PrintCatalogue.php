<?php

namespace App\Support;

use App\Models\BoardCard;
use App\Models\Character;
use App\Models\EntityCard;
use App\Models\PlayerCard;
use App\Models\Scenario;
use App\Models\StoryBeat;
use App\Models\TownAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Every card the tool can print, wherever it lives, under one kind of key —
 * which is what lets the print pool hold cards from anywhere.
 *
 * A pool key is the same `group:id` the print picker uses, with one
 * difference: a player card is `player:` whichever of its owner's lists it
 * came out of. A built deck's picker calls its kit `extras` and a domain's
 * calls its upgrades `upgrade`, but it is one card in one table either way, so
 * adding it from either page is adding the same card.
 *
 * The sheets for a scenario, a character and a deck still lay their own cards
 * out; this only answers "what is this key" and "what is there", so the pool
 * and the catalogue it adds from cannot disagree about either.
 */
class PrintCatalogue
{
    /** The pool's groups, in the order its picker lists them. */
    public const GROUPS = [
        'setup' => 'Setup cards',
        'entity' => 'Entity cards',
        'board' => 'Board cards',
        'beats' => 'Story beats',
        'town' => 'Town cards',
        'character' => 'Character cards',
        'player' => 'Player cards',
    ];

    /** Picker groups that are another group's cards under a deck's own name. */
    private const ALIASES = [
        'extras' => 'player',
        'upgrade' => 'player',
    ];

    public static function poolKey(string $group, int $id): string
    {
        return (self::ALIASES[$group] ?? $group).':'.$id;
    }

    /**
     * A key from anywhere, as the pool stores it — or null when it names no
     * group the pool knows, rather than a guess at one.
     *
     * @return array{0: string, 1: int}|null
     */
    public static function parse(string $key): ?array
    {
        if (preg_match('/^([a-z]+):([0-9]+)$/', trim($key), $m) !== 1) {
            return null;
        }

        $group = self::ALIASES[$m[1]] ?? $m[1];

        return isset(self::GROUPS[$group]) ? [$group, (int) $m[2]] : null;
    }

    /**
     * The cards behind a set of keys, loaded a group at a time, keyed by pool
     * key. A key whose card is gone is simply not in the answer.
     *
     * @param  iterable<array{0: string, 1: int}>  $keys
     * @return Collection<string, Model>
     */
    public function find(iterable $keys): Collection
    {
        $byGroup = [];

        foreach ($keys as [$group, $id]) {
            $byGroup[$group][] = $id;
        }

        $found = collect();

        foreach ($byGroup as $group => $ids) {
            foreach ($this->query($group)->whereKey($ids)->get() as $model) {
                if ($group === 'setup' && ! $model->hasSetup()) {
                    continue;
                }

                $found[$group.':'.$model->getKey()] = $model;
            }
        }

        return $found;
    }

    /**
     * Every printable card there is, as rows for the pool's "add cards" list:
     * names and where each one lives, nothing rendered.
     */
    public function entries(): Collection
    {
        $entries = collect();

        foreach (array_keys(self::GROUPS) as $group) {
            foreach ($this->query($group)->get() as $model) {
                if ($group === 'setup' && ! $model->hasSetup()) {
                    continue;
                }

                $entries->push(['key' => $group.':'.$model->getKey(), 'group' => $group] + $this->describe($group, $model));
            }
        }

        return $entries;
    }

    /**
     * What the picker shows for a card: its name, where it lives and how many
     * copies its own deck calls for.
     *
     * @return array{name: string, source: string, qty: int, is_placeholder: bool}
     */
    public function describe(string $group, Model $model): array
    {
        [$name, $source, $qty, $placeholder] = match ($group) {
            'setup' => [$model->name.' setup', $model->name, 1, false],
            'entity', 'board' => [
                $model->name,
                $model->scenario?->name ?? ($model->module ? $model->module->name.' (module)' : 'No owner'),
                $model->qty,
                $model->is_placeholder,
            ],
            'beats' => [$model->order.'. '.$model->name, $model->scenario?->name ?? 'No scenario', 1, false],
            'town' => [$model->name, $model->scenario?->name ?? 'No scenario', 1, false],
            'character' => [$model->name, $model->name, 1, $model->is_placeholder],
            'player' => [
                $model->name,
                $model->character?->name ?? ($model->domain ? $model->domain->name.' (domain)' : 'No owner'),
                $model->qty,
                $model->is_placeholder,
            ],
        };

        return [
            'name' => $name ?: 'Untitled',
            'source' => $source,
            'qty' => max(1, (int) $qty),
            'is_placeholder' => (bool) $placeholder,
        ];
    }

    /** The card face, the same shape every other print page hands the sheet. */
    public function present(string $group, Model $model, CardPresenter $presenter): array
    {
        return match ($group) {
            'setup' => ['kind' => 'setup'] + $presenter->setupCard($model),
            'entity' => ['kind' => 'entity'] + $presenter->entityCard($model),
            'board' => ['kind' => 'board'] + $presenter->boardCard($model),
            'beats' => ['kind' => 'beat'] + $presenter->storyBeat($model),
            'town' => ['kind' => 'town'] + $presenter->townAction($model),
            'character' => ['kind' => 'character'] + $presenter->character($model),
            'player' => ['kind' => 'player'] + $presenter->playerCard($model),
        };
    }

    private function query(string $group): Builder
    {
        return match ($group) {
            'setup' => Scenario::query()->orderBy('name'),
            'entity' => EntityCard::with(['faces.cardType', 'addedByBeat', 'scenario', 'module'])->orderBy('sort')->orderBy('name'),
            'board' => BoardCard::with(['addedByBeat', 'scenario', 'module'])->orderBy('sort'),
            'beats' => StoryBeat::with('scenario')->orderBy('order'),
            'town' => TownAction::with('scenario')->orderBy('sort'),
            'character' => Character::query()->orderBy('sort')->orderBy('name'),
            'player' => PlayerCard::with(['character', 'domain'])->orderBy('sort')->orderBy('id'),
        };
    }
}
