<?php

namespace App\Console\Commands;

use App\Models\BoardCard;
use App\Models\CardType;
use App\Models\Character;
use App\Models\Domain;
use App\Models\EntityCard;
use App\Models\Keyword;
use App\Models\Module;
use App\Models\PlayerCard;
use App\Models\RuleDocument;
use App\Models\RulesConfig;
use App\Models\Scenario;
use App\Models\StoryBeat;
use App\Models\Tale;
use App\Models\TownAction;
use App\Support\Colour;
use App\Support\PlayerScaled;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Loads the design folder (the markdown rules and the JSON data) into the database.
 * Safe to re-run: everything is matched on a natural key and updated in place.
 */
class ImportDesign extends Command
{
    protected $signature = 'design:import
                            {--path= : Design folder to read from (defaults to design/)}
                            {--fresh : Delete existing scenarios first instead of updating them}';

    protected $description = 'Import the design folder (rules markdown and scenario JSON) into the database';

    /**
     * Editing metadata for the tunable numbers. rules-config.json holds the values;
     * this holds what they mean, so the UI can group and explain them.
     * "decided" entries come from rules/05-decisions-and-open-questions.md.
     */
    private const CONFIG_META = [
        'startingOmen' => ['Starting omen', 'omen', 'Omen in the pool at setup, so the first reveal is not empty.', true],
        'omenPerCardPlayedRange' => ['Omen per card played', 'omen', 'Range of omen icons a player card can carry.', true],
        'omenPerTownAction' => ['Omen per town action', 'omen', 'Omen added each time a player uses a town action.', true],
        'omenAtEndOfRound' => ['Omen at end of round', 'omen', 'The passing of time.', true],
        'omenRemovedPerBoardKill' => ['Omen removed per board kill', 'omen', 'Destroying a board card bleeds omen off the pool.', true],
        'goldPouchCapacity' => ['Gold pouch capacity', 'gold', 'Gold a pouch carries between rounds.', true],
        'goldCarriesOver' => ['Gold carries over', 'gold', 'Decided: gold does not carry over. Off, the Gold step tops each player up to the gold on their character card ("up to their remaining max"), so unspent gold never builds up; on, it adds that gold on top.', false],
        'emptyDeckDreadIncrease' => ['Dread increase on empty deck', 'dread', 'Raise Dread X when the entity deck is reshuffled.', true],
        'storyCardsCancellable' => ['Story cards can be cancelled', 'cards', 'Open question 9: consider allowing side effects only.', true],
        'splitCardTypePerHalf' => ['Split cards have a type per half', 'cards', 'Open question 2: type per half lets Redirect change a card type.', true],
        // v3, the player side. Hand size moved onto the character card.
        'deckSize' => ['Deck size', 'players', 'A deck is this many signature cards plus this many domain cards.', true],
        'neutralFillsDomainSlots' => ['Neutral cards fill domain slots', 'players', 'Colourless cards take domain slots and do not add to the total.', true],
        'maxCopiesPerDomainCard' => ['Max copies of one domain card', 'players', 'The most copies of a single domain card one deck may take. Empty means no cap of its own: the pool\'s print run is the only limit. Not decided — set it when playtesting says what it should be.', true],
        'shopPurchaseDestination' => ['Where a bought card goes', 'players', 'Decided: a bought card goes on top of its buyer\'s deck.', false],
        'shopRowSize' => ['Shop cards showing', 'players', 'Decided: each player has a shop deck, and only this many of it are face up to buy from at a time.', false],
        'playerDeckOutReshuffle' => ['Reshuffle when a player deck runs out', 'players', 'Shuffle the discard pile into a new deck rather than stalling.', true],
        'playerDeckOutOmen' => ['Omen added on a player deck-out', 'players', 'Omen added to the pool each time a player reshuffles.', true],
        'maxHirelingsInPlay' => ['Max Hirelings in play', 'players', 'How many Hirelings one player may have in play at once. The Hireling rules are placeholders in the designer\'s brief, this number among them.', true],
    ];

    /**
     * Which list each role is written in. A character file holds the first
     * three, a domain file the last two — 'upgrades' reads the same in both.
     */
    private const CARD_LISTS = [
        PlayerCard::ROLE_KIT => 'kit',
        PlayerCard::ROLE_SIGNATURE => 'signatureCards',
        PlayerCard::ROLE_DOMAIN => 'cards',
        PlayerCard::ROLE_UPGRADE => 'upgrades',
        PlayerCard::ROLE_REWARD => 'rewards',
    ];

    /** Where a card of each role starts when the file does not say. */
    private const DEFAULT_START_ZONES = [
        PlayerCard::ROLE_KIT => 'play',
        PlayerCard::ROLE_SIGNATURE => 'deck',
        PlayerCard::ROLE_DOMAIN => 'deck',
        PlayerCard::ROLE_UPGRADE => 'upgrade',
        PlayerCard::ROLE_REWARD => 'deck',
    ];

    public function handle(): int
    {
        $path = rtrim($this->option('path') ?: base_path('design'), '/');

        if (! is_dir($path)) {
            $this->error("Design folder not found: {$path}");

            return self::FAILURE;
        }

        DB::transaction(function () use ($path) {
            $this->importConfig("{$path}/data/rules-config.json");
            $this->importCardTypes("{$path}/data/card-types.json");
            $this->importKeywords("{$path}/data/keywords.json");
            $this->importRuleDocuments("{$path}/rules");

            if ($this->option('fresh')) {
                Scenario::query()->get()->each->delete();
            }

            foreach (glob("{$path}/data/*.json") as $file) {
                $data = $this->readJson($file);

                // Scenario files are the ones describing an entity.
                if (! isset($data['entityType'])) {
                    continue;
                }

                $this->importScenario($data);
            }

            // A Tale before the modules, because a Tale's module names its Tale;
            // its steps after them, because a step names its modules.
            $tales = $this->importTales("{$path}/data/tales");
            $this->importModules("{$path}/data/modules");
            $this->importTaleSteps($tales);
            // Before the characters: a character file names the domains it
            // draws from, and they have to exist to be named.
            $this->importDomains("{$path}/players/domains");
            $this->importCharacters("{$path}/players");
        });

        $this->info('Design imported.');
        $this->line(sprintf(
            '  %d scenarios, %d modules, %d tales, %d entity cards, %d board cards, %d beats, %d characters, %d domains, %d player cards, %d rules documents, %d tunable values.',
            Scenario::count(),
            Module::count(),
            Tale::count(),
            EntityCard::count(),
            BoardCard::count(),
            StoryBeat::count(),
            Character::count(),
            Domain::count(),
            PlayerCard::count(),
            RuleDocument::count(),
            RulesConfig::count(),
        ));

        return self::SUCCESS;
    }

    private function readJson(string $file): array
    {
        return json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    }

    private function importConfig(string $file): void
    {
        if (! is_file($file)) {
            return;
        }

        $sort = 0;
        $seen = [];

        foreach ($this->readJson($file) as $key => $value) {
            if (str_starts_with($key, '_')) {
                continue;
            }

            $seen[] = $key;

            [$label, $group, $description, $isPlaceholder] = self::CONFIG_META[$key]
                ?? [Str::headline($key), 'general', null, true];

            RulesConfig::updateOrCreate(
                ['key' => $key],
                [
                    'label' => $label,
                    'group' => $group,
                    'value_type' => RulesConfig::typeFor($value),
                    'value' => ['v' => $value],
                    'description' => $description,
                    'is_placeholder' => $isPlaceholder,
                    'sort' => $sort++,
                ],
            );
        }

        // The design folder is the source of truth for which numbers exist, so a
        // key it has dropped (v3 removed handSize) is dropped here too. Without
        // this the next export would write it straight back.
        RulesConfig::whereNotIn('key', $seen)->delete();
    }

    /**
     * One design-file value that may be a number or an equation, as the two
     * columns that hold it.
     *
     * The equation column is null for a plain number, which is what every one
     * of these was before and still is by default. When an equation is written
     * the number column keeps the default, because the file holds the value in
     * use and nothing else: the number is only what the editor puts back if the
     * designer turns the equation off.
     *
     * @return array<string, int|string|null>
     */
    private function scaled(string $column, mixed $value, int $default): array
    {
        $scaled = PlayerScaled::fromDesign($value, $default);

        return [
            $column => $scaled->number ?? $default,
            $column.'_equation' => $scaled->equation,
        ];
    }

    private function importCardTypes(string $file): void
    {
        if (! is_file($file)) {
            return;
        }

        foreach ($this->readJson($file)['types'] ?? [] as $i => $type) {
            $this->importCardType($type, $i, null);
        }
    }

    /**
     * One card type. A scenario's own goes through the same door with its
     * scenario, and moves to it if the shared library used to hold it — the
     * slug is what identifies a type, whoever ends up owning it.
     */
    private function importCardType(array $type, int $sort, ?Scenario $scenario): CardType
    {
        return CardType::updateOrCreate(
            ['slug' => $type['id']],
            [
                'scenario_id' => $scenario?->id,
                'name' => $type['name'],
                'description' => $type['description'] ?? null,
                // As written. A file with no colour leaves the type's own
                // alone rather than clearing it: the key is optional, not a
                // way of saying "no colour".
                ...(array_key_exists('colour', $type) ? ['colour' => Colour::normalise($type['colour'])] : []),
                ...(array_key_exists('icon', $type) ? ['icon' => $type['icon']] : []),
                'sort' => $type['sort'] ?? $sort,
            ],
        );
    }

    /**
     * The keyword library. Like the card types, a keyword is matched on its
     * token and updated in place: a keyword the file has dropped is left alone
     * rather than deleted, because card text may still be typing it.
     */
    private function importKeywords(string $file): void
    {
        if (! is_file($file)) {
            return;
        }

        foreach ($this->readJson($file)['keywords'] ?? [] as $i => $keyword) {
            Keyword::updateOrCreate(
                ['token' => $keyword['token']],
                [
                    'name' => $keyword['name'],
                    'icon' => $keyword['icon'] ?? null,
                    'show_name' => $keyword['showName'] ?? true,
                    'plain' => $keyword['plain'] ?? null,
                    'description' => $keyword['description'] ?? null,
                    'is_placeholder' => $keyword['isPlaceholder'] ?? true,
                    'sort' => $keyword['sort'] ?? $i,
                ],
            );
        }
    }

    private function importRuleDocuments(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob("{$dir}/*.md") as $i => $file) {
            $body = file_get_contents($file);
            $slug = Str::of(basename($file, '.md'))->slug()->value();

            $document = RuleDocument::firstOrNew(['slug' => $slug]);

            // Keep the designer's edits: only overwrite when the file has moved on.
            if ($document->exists && $document->body !== $body) {
                $document->snapshot('Replaced by design:import');
            }

            $document->fill([
                'title' => $this->headingOf($body) ?? Str::headline(preg_replace('/^\d+-/', '', $slug)),
                'body' => $body,
                'sort' => $i,
            ])->save();
        }
    }

    private function headingOf(string $markdown): ?string
    {
        return preg_match('/^#\s+(.+)$/m', $markdown, $m) ? trim($m[1]) : null;
    }

    /** Every entity card carries an arrow in v2. Anything else defaults to top. */
    private function arrow(array $card): string
    {
        return in_array($card['arrow'] ?? null, ['top', 'bottom'], true) ? $card['arrow'] : 'top';
    }

    /**
     * The most an X-cost card's X can come to, as `"omenMax": 3`. Only an X card
     * has one — a printed cost is already its own number — and a file without
     * the key clears it, the way every optional key here does.
     */
    private function xMax(array $card, bool $isX): ?int
    {
        $max = $card['omenMax'] ?? null;

        return $isX && is_numeric($max) && (int) $max > 0 ? (int) $max : null;
    }

    private function importModules(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $types = CardType::pluck('id', 'slug');

        foreach (glob("{$dir}/*.json") as $i => $file) {
            $data = $this->readJson($file);

            $module = Module::updateOrCreate(
                ['slug' => $data['id']],
                [
                    'name' => $data['name'],
                    'status' => $data['status'] ?? null,
                    'theme' => $data['theme'] ?? null,
                    'set_icon' => $data['setIcon'] ?? null,
                    'compatible_scenarios' => $data['compatibleScenarios'] ?? [],
                    'traits' => $data['traits'] ?? [],
                    'setup' => $data['setup'] ?? null,
                    'sort' => $i,
                    // A Tale's own module names its Tale. A file without the
                    // key is a shared module, whatever the database said.
                    'tale_id' => $this->taleIdFor($data, $file),
                ],
            );

            foreach ($data['entityCards'] ?? [] as $j => $card) {
                $isX = ($card['omenCost'] ?? null) === 'X' || ($card['layout'] ?? '') === 'x-cost';

                $model = EntityCard::updateOrCreate(
                    ['module_id' => $module->id, 'name' => $card['name']],
                    [
                        'scenario_id' => null,
                        'qty' => $card['qty'] ?? 1,
                        'layout' => $card['layout'] ?? 'single',
                        'omen_cost' => $isX ? null : (int) ($card['omenCost'] ?? 0),
                        'omen_is_x' => $isX,
                        'omen_x_max' => $this->xMax($card, $isX),
                        'traits' => $card['traits'] ?? [],
                        'arrow' => $this->arrow($card),
                        'is_placeholder' => $card['isPlaceholder'] ?? true,
                        'sort' => $j,
                    ],
                );

                $model->faces()->delete();

                $split = ($card['layout'] ?? 'single') === 'split';

                foreach ($card['faces'] ?? [] as $k => $face) {
                    $model->faces()->create([
                        'half' => $face['position'] ?? $face['half'] ?? ($split ? ['top', 'bottom'][$k] ?? 'top' : 'single'),
                        'card_type_id' => $types[$face['type'] ?? ''] ?? null,
                        'text' => $face['text'] ?? null,
                        'sort' => $k,
                    ]);
                }
            }

            foreach ($data['boardCards'] ?? [] as $j => $card) {
                BoardCard::updateOrCreate(
                    ['module_id' => $module->id, 'name' => $card['name']],
                    [
                        'scenario_id' => null,
                        'qty' => $card['qty'] ?? 1,
                        'health' => isset($card['health']) ? (string) $card['health'] : null,
                        'traits' => $card['traits'] ?? [],
                        'text' => $card['text'] ?? null,
                        'sort' => $j,
                    ],
                );
            }

            // Districts the module adds to the town when it is chosen.
            foreach ($data['town'] ?? [] as $j => $action) {
                TownAction::updateOrCreate(
                    ['module_id' => $module->id, 'name' => $action['name']],
                    [
                        'scenario_id' => null,
                        'effect' => $action['effect'] ?? null,
                        'gold_cost' => $action['goldCost'] ?? null,
                        'omen' => $action['omen'] ?? 1,
                        'note' => $action['note'] ?? null,
                        'sort' => $j,
                    ],
                );
            }
        }
    }

    /** The Tale a module file names, or null — reported when it names one that is not there. */
    private function taleIdFor(array $data, string $file): ?int
    {
        if (! isset($data['tale'])) {
            return null;
        }

        $id = Tale::where('slug', $data['tale'])->value('id');

        if ($id === null) {
            $this->warn('  '.basename($file).' names the Tale "'.$data['tale'].'", which is not in the design folder; imported as a shared module.');
        }

        return $id;
    }

    /**
     * The Tales: one file each in data/tales. Everything a Tale owns except its
     * steps, which name modules and so wait for them. Within a Tale, whatever
     * the file dropped is dropped; a Tale with no file at all is kept and
     * reported, the same as a module the folder does not have.
     *
     * @return array<string, array{tale: Tale, steps: array}> the steps still to import, by slug
     */
    private function importTales(string $dir): array
    {
        $files = is_dir($dir) ? glob("{$dir}/*.json") : [];
        $pending = [];

        foreach ($files as $i => $file) {
            $data = $this->readJson($file);

            $tale = Tale::updateOrCreate(
                ['slug' => $data['id']],
                [
                    'name' => $data['name'],
                    'status' => $data['status'] ?? null,
                    'overview' => $data['overview'] ?? null,
                    'set_icon' => $data['setIcon'] ?? null,
                    'is_placeholder' => $data['isPlaceholder'] ?? true,
                    'sort' => $i,
                ],
            );

            // Modes and log entries are matched to the rows already there by
            // name, never deleted and written afresh: a playthrough points at
            // its mode and keys its log by entry, so recreating them would wipe
            // every playthrough of the Tale.
            $this->matchRows($tale->modes()->get(), $data['modes'] ?? [], 'name', fn ($mode, $j) => [
                'name' => $mode['name'],
                'text' => $mode['text'] ?? null,
                'is_placeholder' => $mode['isPlaceholder'] ?? true,
                'sort' => $j,
            ], fn (array $attributes) => $tale->modes()->create($attributes));

            $this->matchRows($tale->logEntries()->get(), $data['logEntries'] ?? [], 'name', fn ($entry, $j) => [
                'name' => $entry['name'],
                'kind' => $entry['kind'] ?? 'note',
                'scope' => $entry['scope'] ?? 'party',
                'is_placeholder' => $entry['isPlaceholder'] ?? true,
                'sort' => $j,
            ], fn (array $attributes) => $tale->logEntries()->create($attributes));

            $slugs = [];
            foreach ($data['cards'] ?? [] as $j => $card) {
                $slug = $card['id'] ?? Str::slug($card['name']);
                $slugs[] = $slug;

                $tale->cards()->updateOrCreate(['slug' => $slug], [
                    'name' => $card['name'],
                    'qty' => $card['qty'] ?? 1,
                    'type_line' => $card['typeLine'] ?? null,
                    'text' => $card['text'] ?? null,
                    'flavour' => $card['flavour'] ?? null,
                    'traits' => $card['traits'] ?? [],
                    'is_placeholder' => $card['isPlaceholder'] ?? true,
                    'sort' => $j,
                ]);
            }
            $tale->cards()->whereNotIn('slug', $slugs)->delete();

            $rewards = $this->importCards($data, PlayerCard::TALE_ROLES, ['tale_id' => $tale->id], PlayerCard::ROLE_REWARD);
            $tale->rewardCards()->whereNotIn('slug', $rewards)->delete();

            $pending[$tale->slug] = ['tale' => $tale, 'steps' => $data['steps'] ?? []];
        }

        // A Tale the folder does not have is kept and named, never deleted: the
        // import keeps every scenario, module, character and domain it has no
        // file for, and a Tale would take its whole box with it.
        foreach (Tale::whereNotIn('slug', array_keys($pending))->orderBy('name')->get() as $kept) {
            $this->warn("  The Tale \"{$kept->name}\" is in the editor but not in the design folder; kept as it is. Export to write it out.");
        }

        return $pending;
    }

    /**
     * Each Tale's scenarios, in order. A step naming a scenario that is not
     * there is skipped and a module that is not there is dropped from its step
     * — both reported, neither invented.
     *
     * @param  array<string, array{tale: Tale, steps: array}>  $pending
     */
    private function importTaleSteps(array $pending): void
    {
        $scenarios = Scenario::pluck('id', 'slug');
        $modules = Module::pluck('id', 'slug');

        foreach ($pending as $slug => ['tale' => $tale, 'steps' => $steps]) {
            // A step is matched to the one already there for the same scenario,
            // so a playthrough's results stay on the scenario they were played
            // on, even when the file reorders the Tale.
            $unused = $tale->steps()->get();
            $kept = [];

            foreach (array_values($steps) as $j => $data) {
                $scenarioId = $scenarios[$data['scenario'] ?? ''] ?? null;

                if ($scenarioId === null) {
                    $this->warn("  {$slug}: step ".($j + 1).' names the scenario "'.($data['scenario'] ?? '').'", which is not in the design folder; skipped.');

                    continue;
                }

                $attributes = [
                    'scenario_id' => $scenarioId,
                    'intro' => $data['intro'] ?? null,
                    'outro' => $data['outro'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'sort' => $j,
                ];

                $step = $unused->first(fn ($s) => $s->scenario_id === $scenarioId);

                if ($step) {
                    $unused = $unused->reject(fn ($s) => $s->is($step));
                    $step->update($attributes);
                    $step->modules()->detach();
                } else {
                    $step = $tale->steps()->create($attributes);
                }

                $kept[] = $step->id;

                $attach = [];
                foreach (array_values($data['modules'] ?? []) as $k => $module) {
                    if (! isset($modules[$module])) {
                        $this->warn("  {$slug}: step ".($j + 1)." names the module \"{$module}\", which is not in the design folder; left out.");

                        continue;
                    }

                    $attach[$modules[$module]] = ['sort' => $k];
                }

                $step->modules()->attach($attach);
            }

            // What the file dropped goes, and its results with it — that is
            // the file saying the scenario is no longer in the Tale.
            $tale->steps()->whereNotIn('id', $kept)->delete();
        }
    }

    /**
     * Update the rows already there in place, matched on one field, create
     * what the file adds and delete what it dropped. Ids survive, so whatever
     * points at a row — a playthrough's mode, its log keyed by entry — still
     * points at the right one after an import.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $existing
     * @param  array<int, array>  $rows
     */
    private function matchRows($existing, array $rows, string $key, callable $attributes, callable $create): void
    {
        $unused = $existing;

        foreach (array_values($rows) as $j => $row) {
            $values = $attributes($row, $j);
            $match = $unused->first(fn ($model) => $model->{$key} === $values[$key]);

            if ($match) {
                $unused = $unused->reject(fn ($model) => $model->is($match));
                $match->update($values);
            } else {
                $create($values);
            }
        }

        $unused->each->delete();
    }

    /**
     * The player side: one file per character, holding the character card, the
     * kit, the 20 signature cards and the upgrades set aside for the Smithy.
     */
    private function importCharacters(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob("{$dir}/*.json") as $i => $file) {
            $data = $this->readJson($file);

            if (! isset($data['signatureCards'])) {
                continue;
            }

            $character = Character::updateOrCreate(
                ['slug' => $data['id']],
                [
                    'name' => $data['name'],
                    'title' => $data['title'] ?? null,
                    'story' => $data['story'] ?? null,
                    'status' => $data['status'] ?? null,
                    'identity' => $data['identity'] ?? null,
                    // The character card's two colours, written as a gradient
                    // from one to the other. Either may be missing.
                    'colour' => Colour::normalise($data['colours']['from'] ?? null),
                    'colour_secondary' => Colour::normalise($data['colours']['to'] ?? null),
                    ...$this->scaled('health', $data['health'] ?? null, 10),
                    ...$this->scaled('hand_size', $data['handSize'] ?? null, 5),
                    ...$this->scaled('gold_per_round', $data['goldPerRound'] ?? null, 2),
                    'ability_name' => $data['ability']['name'] ?? null,
                    'ability_text' => $data['ability']['text'] ?? null,
                    'extra_abilities' => Character::normaliseAbilities($data['extraAbilities'] ?? null),
                    'notes' => $data['notes'] ?? [],
                    'sort' => $i,
                ],
            );

            $slugs = $this->importCards(
                $data,
                PlayerCard::CHARACTER_ROLES,
                ['character_id' => $character->id],
                'signature',
            );

            // A card the file has dropped should not linger in the editor.
            $character->cards()->whereNotIn('slug', $slugs)->delete();
        }
    }

    /**
     * A domain file: the shared pool that fills the other half of a deck, plus
     * any upgrades the Smithy swaps into it. Same card shape as a character
     * file, written in two lists rather than three.
     */
    private function importDomains(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob("{$dir}/*.json") as $i => $file) {
            $data = $this->readJson($file);

            if (! isset($data['cards'])) {
                continue;
            }

            $domain = Domain::updateOrCreate(
                ['slug' => $data['id']],
                [
                    'name' => $data['name'],
                    'title' => $data['title'] ?? null,
                    'status' => $data['status'] ?? null,
                    'identity' => $data['identity'] ?? null,
                    // The domain's own two colours, written as a gradient from
                    // one to the other, the same shape a character's take.
                    'colour' => Colour::normalise($data['colours']['from'] ?? null),
                    'colour_secondary' => Colour::normalise($data['colours']['to'] ?? null),
                    'set_icon' => $data['setIcon'] ?? null,
                    'is_neutral' => $data['neutral'] ?? false,
                    'notes' => $data['notes'] ?? [],
                    'is_placeholder' => $data['isPlaceholder'] ?? true,
                    'sort' => $i,
                ],
            );

            $slugs = $this->importCards(
                $data,
                PlayerCard::DOMAIN_ROLES,
                ['domain_id' => $domain->id],
                $domain->defaultOrigin(),
            );

            $domain->cards()->whereNotIn('slug', $slugs)->delete();
        }
    }

    /**
     * The cards of one owner, read from the lists its file is written in. The
     * owner key is what decides whether these are a character's or a domain's:
     * a card has one or the other, never both.
     *
     * @param  array<int, string>  $roles
     * @param  array<string, int>  $owner
     * @return array<int, string> the slugs the file holds
     */
    private function importCards(array $data, array $roles, array $owner, string $defaultOrigin): array
    {
        $sort = 0;
        $slugs = [];

        foreach ($roles as $role) {
            foreach ($data[self::CARD_LISTS[$role]] ?? [] as $card) {
                $slug = $card['id'] ?? Str::slug($card['name']);
                $slugs[] = $slug;

                PlayerCard::updateOrCreate(
                    $owner + ['slug' => $slug],
                    [
                        'name' => $card['name'],
                        'qty' => $card['qty'] ?? 1,
                        'role' => $role,
                        'origin' => $card['origin'] ?? $defaultOrigin,
                        'type' => $card['type'] ?? 'action',
                        'gold_cost' => $card['goldCost'] ?? 0,
                        'omen_icons' => $card['omenIcons'] ?? 0,
                        // A Hireling's two numbers. Read as written, including
                        // on a card that is not a Hireling: the design folder
                        // is the source of truth and what does not line up is
                        // reported rather than corrected.
                        'uses' => $card['uses'] ?? null,
                        'sacrifice_value' => $card['sacrificeValue'] ?? null,
                        'shop_cost' => $card['shopCost'] ?? null,
                        'start_zone' => $card['startZone'] ?? self::DEFAULT_START_ZONES[$role],
                        'text' => $card['text'] ?? null,
                        'traits' => $card['traits'] ?? [],
                        'keywords' => $card['keywords'] ?? [],
                        'upgrades_to' => $card['upgradesTo'] ?? null,
                        'upgrade_of' => $card['upgradeOf'] ?? null,
                        'is_placeholder' => $card['isPlaceholder'] ?? true,
                        'sort' => $sort++,
                    ],
                );
            }
        }

        return $slugs;
    }

    private function importScenario(array $data): void
    {
        $scenario = Scenario::updateOrCreate(
            ['slug' => $data['id']],
            [
                'name' => $data['name'],
                'entity_type' => $data['entityType'],
                'status' => $data['status'] ?? null,
                'overview' => $data['overview'] ?? null,
                'setup' => $data['setup'] ?? null,
                // A number, or an equation counting the players. One key holds
                // either, the way board card health has since v1.
                ...$this->scaled('starting_dread', $data['startingDread'] ?? null, 2),
                // The same, except that a file without the key is a real answer:
                // the scenario takes the shared `startingOmen` tunable number,
                // so both columns are cleared rather than defaulted.
                ...(isset($data['startingOmen'])
                    ? $this->scaled('starting_omen', $data['startingOmen'], 0)
                    : ['starting_omen' => null, 'starting_omen_equation' => null]),
                'dread_effect' => $data['dreadEffect'] ?? null,
                'traits' => $data['traits'] ?? [],
                'win_text' => $data['win'] ?? null,
                'lose_text' => $data['lose'] ?? null,
                'printed_arrows' => $data['printedArrows'] ?? true,
                'modules_required' => $data['moduleRules']['required'] ?? 1,
                'recommended_modules' => $data['moduleRules']['recommended'] ?? [],
                'module_note' => $data['moduleRules']['note'] ?? null,
            ],
        );

        $beats = [];

        foreach ($data['storyBeats'] ?? [] as $beat) {
            $beats[$beat['order']] = StoryBeat::updateOrCreate(
                ['scenario_id' => $scenario->id, 'order' => $beat['order']],
                [
                    'name' => $beat['name'],
                    'flavour' => $beat['flavour'] ?? null,
                    'on_reach' => $beat['onReach'] ?? null,
                    'advance' => $beat['advance'] ?? null,
                    'on_advance' => $beat['onAdvance'] ?? null,
                    ...$this->scaled('dread_change', $beat['dreadChange'] ?? null, 0),
                ],
            );
        }

        // The scenario's own types, before its cards: a card is typed by slug
        // and the type has to exist to be named.
        foreach ($data['cardTypes'] ?? [] as $i => $type) {
            $this->importCardType($type, $i, $scenario);
        }

        $types = CardType::pluck('id', 'slug');

        foreach ($data['entityDeck'] ?? [] as $i => $card) {
            $isX = ($card['omenCost'] ?? null) === 'X' || ($card['layout'] ?? '') === 'x-cost';

            $model = EntityCard::updateOrCreate(
                ['scenario_id' => $scenario->id, 'name' => $card['name']],
                [
                    'qty' => $card['qty'] ?? 1,
                    'layout' => $card['layout'] ?? 'single',
                    'omen_cost' => $isX ? null : (int) ($card['omenCost'] ?? 0),
                    'omen_is_x' => $isX,
                    'omen_x_max' => $this->xMax($card, $isX),
                    'traits' => $card['traits'] ?? [],
                    'added_by_beat_id' => $beats[$card['addedByBeat'] ?? null]->id ?? null,
                    'arrow' => $this->arrow($card),
                    'is_placeholder' => $card['isPlaceholder'] ?? true,
                    'sort' => $i,
                ],
            );

            $model->faces()->delete();

            $faces = $card['faces'] ?? [];
            $split = ($card['layout'] ?? 'single') === 'split';

            foreach ($faces as $j => $face) {
                $model->faces()->create([
                    // The design files call it 'position'; 'half' is accepted too.
                    'half' => $face['position'] ?? $face['half'] ?? ($split ? ['top', 'bottom'][$j] ?? 'top' : 'single'),
                    'card_type_id' => $types[$face['type'] ?? ''] ?? null,
                    'text' => $face['text'] ?? null,
                    'sort' => $j,
                ]);
            }
        }

        $board = array_merge(
            $data['boardSetup'] ?? [],
            array_map(
                fn (array $c) => $c + ['_beat' => $c['beat'] ?? null],
                $data['boardAddedByBeats'] ?? []
            ),
        );

        foreach ($board as $i => $card) {
            BoardCard::updateOrCreate(
                ['scenario_id' => $scenario->id, 'name' => $card['name']],
                [
                    'qty' => $card['qty'] ?? 1,
                    'health' => isset($card['health']) ? (string) $card['health'] : null,
                    'traits' => $card['traits'] ?? [],
                    'text' => $card['text'] ?? null,
                    'added_by_beat_id' => $beats[$card['_beat'] ?? null]->id ?? null,
                    'sort' => $i,
                ],
            );
        }

        foreach ($data['town'] ?? [] as $i => $action) {
            TownAction::updateOrCreate(
                ['scenario_id' => $scenario->id, 'name' => $action['name']],
                [
                    'effect' => $action['effect'] ?? null,
                    'gold_cost' => $action['goldCost'] ?? null,
                    'omen' => $action['omen'] ?? 1,
                    'note' => $action['note'] ?? null,
                    'sort' => $i,
                ],
            );
        }
    }
}
