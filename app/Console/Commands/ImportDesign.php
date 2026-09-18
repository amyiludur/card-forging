<?php

namespace App\Console\Commands;

use App\Models\BoardCard;
use App\Models\CardType;
use App\Models\EntityCard;
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
        'baseGoldPerRound' => ['Base gold per round', 'gold', 'Gold each player generates in the gold step.', true],
        'goldPouchCapacity' => ['Gold pouch capacity', 'gold', 'Gold a pouch carries between rounds.', true],
        'goldCarriesOver' => ['Gold carries over', 'gold', 'Decided: unspent gold is lost at the end of the round.', false],
        'emptyDeckDreadIncrease' => ['Dread increase on empty deck', 'dread', 'Raise Dread X when the entity deck is reshuffled.', true],
        'handSize' => ['Hand size', 'cards', 'Not yet designed. Characters are not designed yet.', true],
        'storyCardsCancellable' => ['Story cards can be cancelled', 'cards', 'Open question 9: consider allowing side effects only.', true],
        'splitCardTypePerHalf' => ['Split cards have a type per half', 'cards', 'Open question 2: type per half lets Redirect change a card type.', true],
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
        });

        $this->info('Design imported.');
        $this->line(sprintf(
            '  %d scenarios, %d entity cards, %d board cards, %d beats, %d rules documents, %d tunable values.',
            Scenario::count(),
            EntityCard::count(),
            BoardCard::count(),
            StoryBeat::count(),
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

        foreach ($this->readJson($file) as $key => $value) {
            if (str_starts_with($key, '_')) {
                continue;
            }

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
    }

    private function valueType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'bool',
            is_array($value) => 'range',
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
                    'arrow' => $card['arrow'] ?? null,
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
