<?php

namespace App\Console\Commands;

use App\Models\CardType;
use App\Models\RuleDocument;
use App\Models\RulesConfig;
use App\Models\Scenario;
use Illuminate\Console\Command;

/**
 * Writes the database back out to the design folder, in the same shape it was
 * imported from. The design folder stays the thing the designer reads and git
 * tracks; the database is what the editor works on. Round trip: import, edit,
 * export, commit.
 */
class ExportDesign extends Command
{
    protected $signature = 'design:export {--path= : Design folder to write to (defaults to design/)}';

    protected $description = 'Export the database back to the design folder as JSON and markdown';

    /** Kept word for word from the designer's own rules-config.json. */
    private const CONFIG_NOTE = 'Every value here is a placeholder for playtesting unless marked otherwise. The platform should treat these as editable parameters and reference them from rules text.';

    public function handle(): int
    {
        $path = rtrim($this->option('path') ?: base_path('design'), '/');

        foreach (["{$path}/data", "{$path}/rules"] as $dir) {
            is_dir($dir) || mkdir($dir, 0o755, true);
        }

        $this->writeJson("{$path}/data/rules-config.json", $this->config());
        $this->writeJson("{$path}/data/card-types.json", ['types' => $this->cardTypes()]);

        foreach (Scenario::with(['storyBeats', 'entityCards.faces.cardType', 'entityCards.addedByBeat', 'boardCards.addedByBeat', 'townActions'])->get() as $scenario) {
            $this->writeJson("{$path}/data/{$scenario->slug}.json", $this->scenario($scenario));
            $this->line("  design/data/{$scenario->slug}.json");
        }

        foreach (RuleDocument::orderBy('sort')->get() as $document) {
            $file = "{$path}/rules/{$document->slug}.md";
            file_put_contents($file, $document->body ?? '');
            $this->line('  design/rules/'.basename($file));
        }

        $this->info('Design exported. Commit the design folder to keep the history in git.');

        return self::SUCCESS;
    }

    private function writeJson(string $file, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // json_encode indents with four spaces; the design folder is written with
        // two, and matching it keeps the git diffs down to the real changes.
        $json = preg_replace_callback(
            '/^(?: {4})+/m',
            fn (array $m): string => str_repeat('  ', strlen($m[0]) / 4),
            $json
        );

        file_put_contents($file, $json."\n");
    }

    private function config(): array
    {
        $out = ['_note' => self::CONFIG_NOTE];

        foreach (RulesConfig::orderBy('sort')->get() as $config) {
            $out[$config->key] = $config->raw_value;
        }

        return $out;
    }

    private function cardTypes(): array
    {
        return CardType::orderBy('sort')->get()
            ->map(fn (CardType $t) => ['id' => $t->slug, 'name' => $t->name, 'description' => $t->description])
            ->all();
    }

    /** Health is free text in the database, but a plain number should stay a number. */
    private function health(?string $value): string|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ctype_digit($value) ? (int) $value : $value;
    }

    private function scenario(Scenario $scenario): array
    {
        return [
            'id' => $scenario->slug,
            'name' => $scenario->name,
            'entityType' => $scenario->entity_type,
            'status' => $scenario->status,
            'overview' => $scenario->overview,
            'startingDread' => $scenario->starting_dread,
            'dreadEffect' => $scenario->dread_effect,
            'printedArrows' => $scenario->printed_arrows,
            'traits' => $scenario->traits ?? [],
            'boardSetup' => $scenario->boardCards->whereNull('added_by_beat_id')->values()->map(fn ($c) => array_filter([
                'name' => $c->name,
                'qty' => $c->qty,
                'health' => $this->health($c->health),
                'traits' => $c->traits ?: null,
                'text' => $c->text,
            ], fn ($v) => $v !== null))->all(),
            'boardAddedByBeats' => $scenario->boardCards->whereNotNull('added_by_beat_id')->values()->map(fn ($c) => array_filter([
                'beat' => $c->addedByBeat?->order,
                'name' => $c->name,
                'qty' => $c->qty,
                'health' => $this->health($c->health),
                'traits' => $c->traits ?: null,
                'text' => $c->text,
            ], fn ($v) => $v !== null))->all(),
            'storyBeats' => $scenario->storyBeats->map(fn ($b) => [
                'order' => $b->order,
                'name' => $b->name,
                'flavour' => $b->flavour,
                'onReach' => $b->on_reach,
                'advance' => $b->advance,
                'onAdvance' => $b->on_advance,
                'dreadChange' => $b->dread_change,
            ])->all(),
            'town' => $scenario->townActions->map(fn ($a) => array_filter([
                'name' => $a->name,
                'effect' => $a->effect,
                'goldCost' => $a->gold_cost,
                'omen' => $a->omen,
                'note' => $a->note,
            ], fn ($v) => $v !== null))->all(),
            'win' => $scenario->win_text,
            'lose' => $scenario->lose_text,
            'entityDeck' => $scenario->entityCards->map(fn ($c) => [
                'name' => $c->name,
                'qty' => $c->qty,
                'layout' => $c->layout,
                'omenCost' => $c->omen_is_x ? 'X' : $c->omen_cost,
                'traits' => $c->traits ?? [],
                'faces' => $c->faces->map(fn ($f) => array_filter([
                    'position' => $f->half === 'single' ? null : $f->half,
                    'type' => $f->cardType?->slug,
                    'text' => $f->text,
                ], fn ($v) => $v !== null))->all(),
                'addedByBeat' => $c->addedByBeat?->order,
                'arrow' => $c->arrow,
            ])->all(),
        ];
    }
}
