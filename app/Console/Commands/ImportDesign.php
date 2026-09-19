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
use App\Models\TownAction;
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
        'empoweredPerPointOfExcess' => ['Empowered per point of excess', 'omen', 'Effect strength the last revealed card gains per point of overshoot.', true],
        'goldPouchCapacity' => ['Gold pouch capacity', 'gold', 'Gold a pouch carries between rounds.', true],
        'goldCarriesOver' => ['Gold carries over', 'gold', 'Decided: unspent gold is lost at the end of the round.', false],
        'emptyDeckDreadIncrease' => ['Dread increase on empty deck', 'dread', 'Raise Dread X when the entity deck is reshuffled.', true],
        'storyCardsCancellable' => ['Story cards can be cancelled', 'cards', 'Open question 9: consider allowing side effects only.', true],
        'splitCardTypePerHalf' => ['Split cards have a type per half', 'cards', 'Open question 2: type per half lets Redirect change a card type.', true],
        // v3, the player side. Hand size moved onto the character card.
        'deckSize' => ['Deck size', 'players', 'A deck is this many signature cards plus this many domain cards.', true],
        'neutralFillsDomainSlots' => ['Neutral cards fill domain slots', 'players', 'Colourless cards take domain slots and do not add to the total.', true],
        'maxCopiesPerDomainCard' => ['Max copies of one domain card', 'players', 'The most copies of a single domain card one deck may take. Empty means no cap of its own: the pool\'s print run is the only limit. Not decided — set it when playtesting says what it should be.', true],
        'shopPurchaseDestination' => ['Where a bought card goes', 'players', 'The designer likes deck-bottom but is not certain: still open.', true],
        'playerDeckOutReshuffle' => ['Reshuffle when a player deck runs out', 'players', 'Shuffle the discard pile into a new deck rather than stalling.', true],
        'playerDeckOutOmen' => ['Omen added on a player deck-out', 'players', 'Omen added to the pool each time a player reshuffles.', true],
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
    ];

    /** Where a card of each role starts when the file does not say. */
    private const DEFAULT_START_ZONES = [
        PlayerCard::ROLE_KIT => 'play',
        PlayerCard::ROLE_SIGNATURE => 'deck',
        PlayerCard::ROLE_DOMAIN => 'deck',
        PlayerCard::ROLE_UPGRADE => 'upgrade',
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

            $this->importModules("{$path}/data/modules");
            // Before the characters: a character file names the domains it
            // draws from, and they have to exist to be named.
            $this->importDomains("{$path}/players/domains");
            $this->importCharacters("{$path}/players");
        });

        $this->info('Design imported.');
        $this->line(sprintf(
            '  %d scenarios, %d modules, %d entity cards, %d board cards, %d beats, %d characters, %d domains, %d player cards, %d rules documents, %d tunable values.',
            Scenario::count(),
            Module::count(),
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
                    'value_type' => $this->valueType($value),
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

    private function valueType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'bool',
            // A list is a range ([0, 2]); an object is a map ({signature: 20}).
            is_array($value) => array_is_list($value) ? 'range' : 'map',
            is_int($value) => 'int',
            is_null($value) => 'int',
            default => 'string',
        };
    }

    private function importCardTypes(string $file): void
    {
        if (! is_file($file)) {
            return;
        }

        foreach ($this->readJson($file)['types'] ?? [] as $i => $type) {
            CardType::updateOrCreate(
                ['slug' => $type['id']],
                ['name' => $type['name'], 'description' => $type['description'] ?? null, 'sort' => $i],
            );
        }
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
        }
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
                    'health' => $data['health'] ?? 10,
                    'hand_size' => $data['handSize'] ?? 5,
                    'gold_per_round' => $data['goldPerRound'] ?? 2,
                    'ability_name' => $data['ability']['name'] ?? null,
                    'ability_text' => $data['ability']['text'] ?? null,
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
                'starting_dread' => $data['startingDread'] ?? 2,
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
                    'dread_change' => $beat['dreadChange'] ?? 0,
                ],
            );
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
